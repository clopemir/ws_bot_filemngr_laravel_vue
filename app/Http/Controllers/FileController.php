<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Exception;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{

    public function store(Request $request) {
        $validated = $request->validate([
            //'file_upload' => 'required|file|max:10240',
            'folder_id' => 'required|exists:folders,id',
            'files.*' => 'required|file|max:20480'
        ]);

        try {
                $folder = Folder::findOrFail($validated['folder_id']);
                $client = Client::findOrFail($folder->client_id);
                //$client = Client::where('id', '=', $folder->client_id)->firstOrFail();

                foreach ($request->file('files') as $uploadedFile) {

                    $folderStoragePath = $folder->getStoragePath();
                    // Disco privado: los archivos solo se sirven con sesión iniciada o enlace firmado.
                    $storedFilePath = $uploadedFile->store($folderStoragePath, 'local');

                    $originalFileName = $uploadedFile->getClientOriginalName();
                    $fileSize = $uploadedFile->getSize(); // Tamaño en bytes
                    $fileMimeType = $uploadedFile->getMimeType(); // o getClientMimeType()

                    File::create([
                        'folder_id' => $folder->id,
                        'original_file_name' => $originalFileName,
                        'file_name' => basename($storedFilePath), // Nombre único generado por Laravel
                        'file_path' => $storedFilePath,        // Ruta relativa al disco para Storage::url()
                        'file_size' => $fileSize,
                        'file_type' => $fileMimeType,
                        'category' => $folder->folder_name,
                        'client_rfc' => $client->client_rfc
                    ]);

                }

            return back()->with('success', 'Archivos subidos exitosamente a la carpeta "' . $folder->folder_name . '".');

        } catch (Exception $e) {
            Log::error("Error al subir archivo: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error al subir el archivo. Revisa el log para más detalles.');
        }

    }

    /**
     * Descarga para usuarios autenticados del panel.
     */
    public function show(File $file) {
        $disk = $file->storageDisk();
        abort_unless($disk, 404);

        return Storage::disk($disk)->download($file->file_path, $file->original_file_name);
    }

    public function destroy(File $file) {

        if ($disk = $file->storageDisk()) {
            Storage::disk($disk)->delete($file->file_path);
        }

        $file->delete();

        return back()->with('success', 'Archivo Borrado');
    }
}
