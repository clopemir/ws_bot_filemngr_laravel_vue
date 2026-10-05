<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Chat;
use App\Models\Client;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\AgentDocumentService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Storage;

class DocumentDeliveryTest extends WhatsAppTestCase
{
    private const CLIENT_TO = '525512345678';

    private Client $otherClient;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->otherClient = Client::create([
            'agent_id' => null, 'client_name' => 'Otra', 'client_lname' => 'Empresa',
            'client_rfc' => 'oem900202xy9', 'client_phone' => '5533334444',
            'client_mail' => 'otra@example.com', 'client_status' => true,
        ]);

        $this->makeFile($this->client, 'constancias', 'constancia.pdf', 'PDF-JUAN');
        $this->makeFile($this->otherClient, 'secretos', 'secreto.pdf', 'PDF-OTRA');
    }

    private function makeFile(Client $client, string $category, string $name, string $content): File
    {
        $folder = Folder::create(['client_id' => $client->id, 'folder_name' => $category]);
        $path = "clientes/{$client->client_rfc}/{$category}/" . md5($name) . '.pdf';
        Storage::disk('local')->put($path, $content);

        return File::create([
            'folder_id' => $folder->id, 'file_name' => basename($path), 'original_file_name' => $name,
            'file_path' => $path, 'category' => $category, 'client_rfc' => $client->client_rfc,
        ]);
    }

    public function test_verified_client_receives_documents_through_signed_links(): void
    {
        $this->verifiedChat();

        $this->tap(Chat::INTENT_ASK_DOC_CATEGORIES);
        $list = collect($this->sentTo(self::CLIENT_TO))->last();
        $rows = $list['interactive']['action']['sections'][0]['rows'];
        $this->assertCount(1, $rows, 'Solo deben listarse las categorías del cliente verificado');
        $this->assertStringNotContainsStringIgnoringCase('pejj', json_encode($list), 'El RFC no debe viajar en los IDs');

        $this->tap($rows[0]['id']);

        $document = collect($this->sentTo(self::CLIENT_TO))->firstWhere('type', 'document');
        $this->assertSame('constancia.pdf', $document['document']['filename']);
        $link = $document['document']['link'];
        $this->assertStringContainsString('signature=', $link);

        $this->get($link)->assertOk()->assertDownload('constancia.pdf');
        $this->assertSame('PDF-JUAN', $this->get($link)->streamedContent());
    }

    public function test_download_links_cannot_be_tampered_or_reused_after_expiry(): void
    {
        $file = File::firstWhere('original_file_name', 'constancia.pdf');
        $other = File::firstWhere('original_file_name', 'secreto.pdf');
        $link = $file->temporaryDownloadUrl();

        $this->get(str_replace("/wa/files/{$file->id}", "/wa/files/{$other->id}", $link))->assertForbidden();
        $this->get(route('whatsapp.files.download', $file))->assertForbidden();

        $this->travel(11)->minutes();
        $this->get($link)->assertForbidden();
    }

    public function test_download_links_survive_a_proxy_that_reports_http(): void
    {
        $link = File::firstWhere('original_file_name', 'constancia.pdf')->temporaryDownloadUrl();

        $this->assertStringStartsWith('https://bot.test/wa/files/', $link);
        $this->get(str_replace('https://bot.test', 'http://127.0.0.1', $link))->assertOk();
    }

    public function test_private_files_have_no_storage_route(): void
    {
        $file = File::first();

        $this->get('/storage/' . $file->file_path)->assertNotFound();
    }

    public function test_typed_option_ids_are_ignored(): void
    {
        $this->verifiedChat();

        // Antes: escribir "ask_doc_cat_<RFC ajeno>" listaba las categorías de otro cliente.
        $this->text('ask_doc_cat_oem900202xy9');
        $this->text(WhatsAppService::categoryOptionId('secretos'));

        $this->assertEmpty(collect($this->sentTo(self::CLIENT_TO))->where('type', 'document'));
        $this->assertSame(Chat::ACTION_CLIENT_OPTIONS, Chat::firstWhere('wa_id', self::CLIENT_WA)->action);
    }

    public function test_category_of_another_client_cannot_be_requested(): void
    {
        $this->verifiedChat()->update(['action' => Chat::ACTION_REQUEST_DOCUMENT_CATEGORY]);

        $this->tap(WhatsAppService::categoryOptionId('secretos'));

        $this->assertEmpty(collect($this->sentTo(self::CLIENT_TO))->where('type', 'document'));
        $this->assertStringContainsString('opción válida', $this->lastTextTo(self::CLIENT_TO));
    }

    public function test_documents_are_not_sent_if_session_ended_before_the_job_runs(): void
    {
        $chat = $this->verifiedChat();
        $chat->clearSession();

        (new \App\Jobs\SendClientDocumentsJob($chat->id, $this->client->id, 'constancias'))
            ->handle(app(WhatsAppService::class));

        $this->assertEmpty($this->sentTo(self::CLIENT_TO));
    }

    public function test_agents_only_find_documents_of_their_own_clients(): void
    {
        $service = app(AgentDocumentService::class);

        $this->assertSame('success', $service->findDocumentByClientAndType($this->agent, 'juan', 'constancias')['status']);
        $this->assertSame('error', $service->findDocumentByClientAndType($this->agent, 'otra empresa', 'secretos')['status']);
    }

    public function test_panel_download_requires_login(): void
    {
        $file = File::first();

        $this->get("/files/{$file->id}")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get("/files/{$file->id}")->assertOk();
    }

    public function test_log_viewer_requires_login(): void
    {
        $this->get('/log-viewer')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/log-viewer')->assertOk();
    }

    public function test_legacy_public_files_are_moved_to_private_disk(): void
    {
        $file = File::first();
        Storage::disk('public')->put($file->file_path, Storage::disk('local')->get($file->file_path));
        Storage::disk('local')->delete($file->file_path);

        $this->artisan('files:make-private')->assertSuccessful();

        Storage::disk('public')->assertMissing($file->file_path);
        Storage::disk('local')->assertExists($file->file_path);
    }
}
