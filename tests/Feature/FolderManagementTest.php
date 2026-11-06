<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Client;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FolderManagementTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;
    private Folder $root;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs(User::factory()->create());

        $agent = Agent::create(['agent_name' => 'Ana', 'agent_lname' => 'A', 'agent_phone' => '5511112222', 'agent_mail' => 'a@example.com']);
        $this->client = Client::create([
            'agent_id' => $agent->id, 'client_name' => 'Juan', 'client_lname' => 'Pérez', 'client_rfc' => 'pejj800101ab1',
            'client_phone' => '5512345678', 'client_mail' => 'j@example.com', 'client_status' => true,
        ]);
        $this->root = Folder::create(['client_id' => $this->client->id, 'folder_name' => 'pejj800101ab1']);
    }

    private function createSubfolder(string $name): Folder
    {
        $this->post('/folders', ['folder_name' => $name, 'parent_id' => $this->root->id])->assertSessionHasNoErrors();

        return Folder::where('folder_name', $name)->firstOrFail();
    }

    private function upload(Folder $folder, string $name = 'doc.pdf'): File
    {
        $this->post('/files', ['folder_id' => $folder->id, 'files' => [UploadedFile::fake()->create($name, 10)]])
            ->assertSessionHas('success');

        return File::latest('id')->firstOrFail();
    }

    public function test_a_new_subfolder_inherits_the_client_and_accepts_uploads(): void
    {
        $folder = $this->createSubfolder('Nóminas 2026');

        $this->assertSame($this->client->id, $folder->client_id);

        $file = $this->upload($folder);

        $this->assertSame('pejj800101ab1', $file->client_rfc);
        $this->assertSame('Nóminas 2026', $file->category);
        Storage::disk('local')->assertExists($file->file_path);
    }

    public function test_legacy_subfolders_without_client_can_receive_uploads(): void
    {
        $folder = $this->createSubfolder('Antigua');
        DB::table('folders')->where('id', $folder->id)->update(['client_id' => null]);

        $this->assertSame('pejj800101ab1', $this->upload($folder->fresh())->client_rfc);
    }

    public function test_migration_backfills_folders_without_client(): void
    {
        $folder = $this->createSubfolder('Antigua');
        DB::table('folders')->where('id', $folder->id)->update(['client_id' => null]);
        DB::table('folders')->where('id', $this->root->id)->update(['client_id' => null]);

        (require database_path('migrations/2026_11_06_120000_backfill_client_id_on_folders.php'))->up();

        $this->assertSame($this->client->id, $folder->fresh()->client_id);
        $this->assertSame($this->client->id, $this->root->fresh()->client_id);
    }

    public function test_duplicate_or_unusable_names_are_rejected(): void
    {
        $this->createSubfolder('Facturas');

        $this->post('/folders', ['folder_name' => 'facturas', 'parent_id' => $this->root->id])->assertSessionHasErrors('folder_name');
        $this->post('/folders', ['folder_name' => '!!!', 'parent_id' => $this->root->id])->assertSessionHasErrors('folder_name');
        $this->post('/folders', ['folder_name' => 'Sin padre'])->assertSessionHasErrors('parent_id');
    }

    public function test_renaming_moves_files_and_updates_the_bot_category(): void
    {
        $folder = $this->createSubfolder('Facturas');
        $file = $this->upload($folder);
        $oldPath = $file->file_path;

        $this->put("/folders/{$folder->id}", ['folder_name' => 'Facturación 2026'])->assertSessionHasNoErrors();

        $file->refresh();
        $folder->refresh();
        $this->assertSame('pejj800101ab1/facturacion-2026', $folder->path);
        $this->assertSame('Facturación 2026', $file->category);
        $this->assertStringStartsWith('clientes/pejj800101ab1/facturacion-2026/', $file->file_path);
        Storage::disk('local')->assertExists($file->file_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_changing_only_capitalization_keeps_files_in_place(): void
    {
        $folder = $this->createSubfolder('facturas');
        $file = $this->upload($folder);
        $path = $file->file_path;

        $this->put("/folders/{$folder->id}", ['folder_name' => 'Facturas'])->assertSessionHasNoErrors();

        $this->assertSame($path, $file->fresh()->file_path);
        $this->assertSame('Facturas', $file->fresh()->category);
        Storage::disk('local')->assertExists($path);
    }

    public function test_deleting_a_folder_with_files_requires_typing_its_name(): void
    {
        $folder = $this->createSubfolder('Facturas');
        $file = $this->upload($folder);

        $this->delete("/folders/{$folder->id}")->assertSessionHasErrors('confirm_name');
        $this->assertNotNull($folder->fresh());
        Storage::disk('local')->assertExists($file->file_path);

        $this->delete("/folders/{$folder->id}", ['confirm_name' => 'Facturas'])
            ->assertRedirect("/folder/{$this->root->path}");

        $this->assertNull($folder->fresh());
        $this->assertNull($file->fresh());
        Storage::disk('local')->assertMissing($file->file_path);
    }

    public function test_an_empty_folder_is_deleted_without_confirmation(): void
    {
        $folder = $this->createSubfolder('Vacía');

        $this->delete("/folders/{$folder->id}")->assertSessionHasNoErrors();

        $this->assertNull($folder->fresh());
    }

    public function test_the_client_root_folder_cannot_be_renamed_or_deleted(): void
    {
        $this->put("/folders/{$this->root->id}", ['folder_name' => 'otro'])->assertSessionHas('error');
        $this->delete("/folders/{$this->root->id}")->assertSessionHas('error');

        $this->assertSame('pejj800101ab1', $this->root->fresh()->folder_name);
    }

    public function test_guests_cannot_manage_folders(): void
    {
        $folder = $this->createSubfolder('Facturas');
        auth()->logout();

        $this->put("/folders/{$folder->id}", ['folder_name' => 'x'])->assertRedirect('/login');
        $this->delete("/folders/{$folder->id}")->assertRedirect('/login');
        $this->get('/search?q=juan')->assertRedirect('/login');
    }

    public function test_global_search_finds_clients_and_files(): void
    {
        $folder = $this->createSubfolder('Facturas');
        $this->upload($folder, 'recibo-enero.pdf');

        $this->getJson('/search?q=juan')->assertOk()->assertJsonFragment(['type' => 'Cliente', 'title' => 'Juan Pérez']);
        $this->getJson('/search?q=recibo')->assertOk()->assertJsonFragment(['type' => 'Archivo', 'title' => 'recibo-enero.pdf']);
        $this->getJson('/search?q=j')->assertOk()->assertExactJson(['results' => []]);
    }
}
