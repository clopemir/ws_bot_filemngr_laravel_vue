<?php

namespace App\Http\Controllers;

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
            'file_upload' => 'required|file|max:10240',
            'folder_id' => 'required|exists:folders,id'
        ]);

        try {
            $folder = Folder::findOrFail($validated['folder_id']);
            $uploadedFile = $request->file('file_upload');

            // 1 Obtener la ruta de almacenamiento
            $folderStoragePath = $folder->getStoragePath();

             // 2. Obtener información del archivo
             $originalFileName = $uploadedFile->getClientOriginalName();
             $fileSize = $uploadedFile->getSize(); // Tamaño en bytes
             $fileMimeType = $uploadedFile->getMimeType(); // o getClientMimeType()

             //3 almacenar el archivo

             $storedFilePath = $uploadedFile->store($folderStoragePath, 'public');
            // $storedFilePath será algo como: "clientes/carpeta_padre/subcarpeta/nombreUnicoGenerado.ext"

            if (!$storedFilePath) {
                throw new Exception('No se pudo almacenar el archivo en el disco.');
            }

            $file = new File([
                'folder_id' => $folder->id,
                'original_file_name' => $originalFileName,
                'file_name' => basename($storedFilePath), // Nombre único generado por Laravel
                'file_path' => $storedFilePath,        // Ruta relativa al disco para Storage::url()
                'file_size' => $fileSize,
                'file_type' => $fileMimeType,
            ]);
            $file->save();

            return redirect()->back()->with('success', 'Archivo "' . $originalFileName . '" subido exitosamente a la carpeta "' . $folder->folder_name . '".');

        } catch (Exception $e) {
            Log::error("Error al subir archivo: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error al subir el archivo: ' . $e->getMessage());
        }

    }

    public function destroy(File $file) {

        Storage::delete($file->file_path);

        $file->delete();

        return back()->with('success', 'Archivo Borrado');
    }
}
