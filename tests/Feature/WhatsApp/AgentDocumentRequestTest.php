<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Agent;
use App\Models\Client;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AgentDocumentRequestTest extends WhatsAppTestCase
{
    private const AGENT_WA = '5215511112222';
    private const AGENT_TO = '525511112222';

    private Client $unassigned;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();

        config([
            'ai.providers' => ['gemini'],
            'services.gemini.api_key' => 'key',
            'services.gemini.url' => 'https://generativelanguage.googleapis.com/v1/x',
        ]);

        // Cliente de otro agente: también debe poder consultarse.
        $otherAgent = Agent::create(['agent_name' => 'Beto', 'agent_lname' => 'B', 'agent_phone' => '5577778888', 'agent_mail' => 'b@example.com']);
        $this->unassigned = Client::create([
            'agent_id' => $otherAgent->id, 'client_name' => 'Ferretería', 'client_lname' => 'El Martillo',
            'client_rfc' => 'fma900202xy9', 'client_phone' => '5533334444', 'client_mail' => 'f@example.com', 'client_status' => true,
        ]);

        $this->makeFile($this->client, 'constancias', 'constancia-juan.pdf');
        $this->makeFile($this->client, 'opiniones', 'opinion-juan.pdf');
        $this->makeFile($this->unassigned, 'constancias', 'constancia-martillo.pdf');
    }

    private function makeFile(Client $client, string $category, string $name): File
    {
        $root = Folder::firstOrCreate(['client_id' => $client->id, 'folder_name' => $client->client_rfc, 'parent_id' => null]);
        $folder = Folder::where('parent_id', $root->id)->where('folder_name', $category)->first()
            ?? Folder::create(['folder_name' => $category, 'parent_id' => $root->id]);

        $path = "clientes/{$client->client_rfc}/{$category}/{$name}";
        Storage::disk('local')->put($path, 'PDF');

        return File::create([
            'folder_id' => $folder->id, 'file_name' => $name, 'original_file_name' => $name, 'file_path' => $path,
            'file_size' => 3, 'file_type' => 'application/pdf', 'category' => $category, 'client_rfc' => $client->client_rfc,
        ]);
    }

    private function documentsSent(): array
    {
        return array_values(array_map(
            fn ($message) => $message['document']['filename'],
            array_filter($this->sentTo(self::AGENT_TO), fn ($message) => ($message['type'] ?? null) === 'document'),
        ));
    }

    private function fakeAi(?array $json = null, ?string $text = null): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [
                ['text' => $text ?? json_encode($json)],
            ]]]]]),
        ]);
    }

    private function aiCalls(): int
    {
        return Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'generativelanguage'))->count();
    }

    public function test_a_clear_request_is_resolved_without_spending_ai_calls(): void
    {
        $this->fakeAi(text: 'no debería usarse');

        $this->text('pásame la constancia de juan perez porfa', self::AGENT_WA);

        $this->assertSame(['constancia-juan.pdf'], $this->documentsSent());
        $this->assertSame(0, $this->aiCalls());
    }

    public function test_agents_can_request_files_of_any_client(): void
    {
        $this->fakeAi(text: 'no debería usarse');

        $this->text('constancia de ferreteria el martillo', self::AGENT_WA);

        $this->assertSame(['constancia-martillo.pdf'], $this->documentsSent());
        $document = collect($this->sentTo(self::AGENT_TO))->firstWhere('type', 'document');
        $this->assertStringContainsString('Ferretería El Martillo', $document['document']['caption']);
    }

    public function test_request_by_rfc_and_typos_work(): void
    {
        $this->fakeAi(text: 'no debería usarse');

        $this->text('opinion PEJJ800101AB1', self::AGENT_WA);
        $this->assertSame(['opinion-juan.pdf'], $this->documentsSent());

        $this->text('constansia de juan peres', self::AGENT_WA);
        $this->assertSame(['opinion-juan.pdf', 'constancia-juan.pdf'], $this->documentsSent());
        $this->assertSame(0, $this->aiCalls());
    }

    public function test_free_form_requests_are_interpreted_by_ai(): void
    {
        $this->fakeAi(['intent' => 'fetch_document', 'client_name' => 'El Martillo', 'document_name' => 'constancias']);

        $this->text('oye necesitaría lo que tengas de situación del Martillo aquel, urge', self::AGENT_WA);

        $this->assertSame(['constancia-martillo.pdf'], $this->documentsSent());
        $this->assertSame(1, $this->aiCalls());
    }

    public function test_same_name_clients_are_disambiguated_by_number(): void
    {
        $this->fakeAi(text: 'no debería usarse');

        $other = Client::create([
            'agent_id' => null, 'client_name' => 'Juan', 'client_lname' => 'López', 'client_rfc' => 'lojj800101ab2',
            'client_phone' => '5500000001', 'client_mail' => 'jl@example.com', 'client_status' => true,
        ]);
        $this->makeFile($other, 'constancias', 'constancia-lopez.pdf');

        $this->text('constancia de juan', self::AGENT_WA);

        $this->assertSame([], $this->documentsSent());
        $this->assertStringContainsString('LOJJ800101AB2', $this->lastTextTo(self::AGENT_TO));

        $options = $this->lastTextTo(self::AGENT_TO);
        $number = str_contains(explode("\n", explode("\n\n", $options)[1])[0], 'López') ? '1' : '2';

        $this->text($number, self::AGENT_WA);

        $this->assertSame(['constancia-lopez.pdf'], $this->documentsSent());
    }

    public function test_follow_up_message_uses_the_last_client(): void
    {
        $this->fakeAi(text: 'no debería usarse');

        $this->text('constancia de juan perez', self::AGENT_WA);
        $this->text('y la opinión?', self::AGENT_WA);

        $this->assertSame(['constancia-juan.pdf', 'opinion-juan.pdf'], $this->documentsSent());
    }

    public function test_asking_without_a_document_lists_the_categories(): void
    {
        $this->fakeAi(text: 'no debería usarse');

        $this->text('archivos de juan perez', self::AGENT_WA);

        $this->assertSame([], $this->documentsSent());
        $this->assertStringContainsString('opiniones', $this->lastTextTo(self::AGENT_TO));
    }

    public function test_requests_still_work_when_no_ai_is_available(): void
    {
        config(['ai.providers' => []]);

        $this->text('constancia de juan perez', self::AGENT_WA);
        $this->assertSame(['constancia-juan.pdf'], $this->documentsSent());

        $this->text('cuanto es el iva de hoy', self::AGENT_WA);
        $this->assertStringContainsString('no está disponible', $this->lastTextTo(self::AGENT_TO));
    }

    public function test_general_questions_go_to_the_ai_chat(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]]),
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [['text' => '{"intent":"chat","client_name":null,"document_name":null}']]]]]])
                ->push(['candidates' => [['content' => ['parts' => [['text' => 'El IVA general es del 16%.']]]]]]),
        ]);

        $this->text('¿cuál es el iva general?', self::AGENT_WA);

        $this->assertSame('El IVA general es del 16%.', $this->lastTextTo(self::AGENT_TO));
    }

    public function test_inactive_agents_lose_access_to_documents(): void
    {
        $this->fakeAi(text: 'no debería usarse');
        $this->agent->update(['agent_status' => false]);

        $this->text('constancia de juan perez', self::AGENT_WA);

        $this->assertSame([], $this->documentsSent());
    }

    public function test_only_the_most_recent_files_are_sent_and_the_agent_is_told(): void
    {
        config(['whatsapp_bot.agent_max_files' => 1]);
        $this->fakeAi(text: 'no debería usarse');
        $this->makeFile($this->client, 'constancias', 'constancia-2.pdf');

        $this->text('constancia de juan perez', self::AGENT_WA);

        $this->assertCount(1, $this->documentsSent());
        $this->assertStringContainsString('de 2 archivos', $this->textsTo(self::AGENT_TO)[0]);
    }
}
