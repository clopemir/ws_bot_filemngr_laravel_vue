<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve un documento a WhatsApp mediante un enlace firmado y de corta duración.
 * La firma (middleware 'signed') impide alterar el ID o reutilizar el enlace después de su vencimiento.
 */
class SignedFileController extends Controller
{
    public function __invoke(File $file): StreamedResponse
    {
        $disk = $file->storageDisk();
        abort_unless($disk, 404);

        return Storage::disk($disk)->download($file->file_path, $file->original_file_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
