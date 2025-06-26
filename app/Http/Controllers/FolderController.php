<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Folder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FolderController extends Controller
{
    public function index() {
        $folders = Folder::with(['children', 'client'])->whereNull('parent_id')->paginate(10);

        return Inertia::render('Folders/Index', [
            'folders' => $folders
        ]);
    }

    public function create() {
        return Inertia::render('Folders/Create');
    }

    public function show(Folder $folder) {

        $folder->load(['files', 'children' => function ($query) { $query->withCount('files'); },]);

        return Inertia::render('Folders/Show', [
            'folder' => $folder
        ]);
    }


    public function showByPath($path) {

        $folder = Folder::where('path', $path)->with(['children', 'files'])->firstOrFail();

        return Inertia::render('Folders/Show', [
            'folder' => $folder
        ]);
    }

    public function store(Request $request) {


        try {

            $validated = $request->validate([
                'folder_name' => 'required|string|max:255',
                'parent_id' => 'nullable|exists:folders,id',
            ]);

            $newFolder = Folder::create($validated);

            $path = $this->getFullPath($newFolder);

            Storage::disk('public')->makeDirectory($path);

            return redirect()->back()->with('success', 'La carpeta se ha creado correcta´');

        } catch (Exception $e) {

            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'No se genero la carpeta');
        }


    }

    public function update(Folder $folder, Request $request) {

    }

    public function destroy(Folder $folder) {
        $folder->delete();

        return redirect()->route('folders.index')->with('success', 'Directorio Eliminado');

    }


    protected function getFullPath(Folder $folder): string {
        $pathParts = [];
        $currentFolder = $folder;

        // Recorre hacia arriba para obtener todos los nombres de las carpetas padre
        while ($currentFolder) {
            array_unshift($pathParts, $currentFolder->folder_name); // Añade al principio del array
            if ($currentFolder->parent_id) {
                $currentFolder = Folder::find($currentFolder->parent_id);
            } else {
                $currentFolder = null; // Llegó a la raíz
            }
        }

        // Une todas las partes con el separador de directorios y prefija con 'clientes'
        return 'clientes/' . implode('/', $pathParts);
    }
}
