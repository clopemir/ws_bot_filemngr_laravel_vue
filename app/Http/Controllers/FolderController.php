<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use App\Services\FolderService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FolderController extends Controller
{
    public function __construct(private FolderService $folders)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));

        $folders = Folder::query()
            ->whereNull('parent_id')
            ->with(['client' => fn ($query) => $query->withCount('files')])
            ->withCount('children')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('folder_name', 'like', "%{$search}%")
                        ->orWhereHas('client', function (Builder $query) use ($search) {
                            $query->where('client_name', 'like', "%{$search}%")
                                ->orWhere('client_lname', 'like', "%{$search}%")
                                ->orWhere('client_rfc', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('folder_name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Folders/Index', [
            'folders' => $folders,
            'search' => $search,
        ]);
    }

    public function show(Folder $folder): Response
    {
        return $this->renderFolder($folder);
    }

    public function showByPath(string $path): Response
    {
        return $this->renderFolder(Folder::where('path', $path)->firstOrFail());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['parent_id' => 'required|exists:folders,id']);
        $parent = Folder::findOrFail($validated['parent_id']);

        $name = $this->validatedName($request, $parent);

        try {
            $this->folders->create($parent, $name);
        } catch (Throwable $e) {
            Log::error('Error al crear la carpeta.', ['parent_id' => $parent->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo crear la carpeta.');
        }

        return back()->with('success', "Carpeta \"{$name}\" creada.");
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        if ($folder->isRoot()) {
            return back()->with('error', 'La carpeta principal del cliente lleva su RFC y no se puede renombrar.');
        }

        $name = $this->validatedName($request, $folder->parent, $folder);

        try {
            $this->folders->rename($folder, $name);
        } catch (Throwable $e) {
            Log::error('Error al renombrar la carpeta.', ['folder_id' => $folder->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo renombrar la carpeta. No se hicieron cambios.');
        }

        // Si se renombró desde su propia página, la URL anterior ya no existe.
        return ($request->boolean('open') ? redirect("/folder/{$folder->path}") : back())
            ->with('success', "Carpeta renombrada a \"{$name}\".");
    }

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        if ($folder->isRoot()) {
            return back()->with('error', 'La carpeta principal del cliente no se puede eliminar.');
        }

        $fileCount = File::whereIn('folder_id', $folder->subtree()->pluck('id'))->count();

        // Borrar documentos es irreversible: hay que escribir el nombre exacto de la carpeta.
        if ($fileCount > 0 && $request->input('confirm_name') !== $folder->folder_name) {
            return back()->withErrors(['confirm_name' => 'Escribe el nombre exacto de la carpeta para confirmar.']);
        }

        $parentPath = $folder->parent->path;

        try {
            $deleted = $this->folders->delete($folder);
        } catch (Throwable $e) {
            Log::error('Error al eliminar la carpeta.', ['folder_id' => $folder->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo eliminar la carpeta.');
        }

        return redirect("/folder/{$parentPath}")->with('success', $deleted > 0
            ? "Carpeta eliminada junto con {$deleted} archivo(s)."
            : 'Carpeta eliminada.');
    }

    private function renderFolder(Folder $folder): Response
    {
        $folder->load([
            'files' => fn ($query) => $query->latest(),
            'children' => fn ($query) => $query->withCount(['files', 'children'])->orderBy('folder_name'),
        ]);

        return Inertia::render('Folders/Show', [
            'folder' => $folder,
            'ancestors' => $folder->ancestors()->map->only(['id', 'folder_name', 'path'])->values(),
            'clientInfo' => $folder->owningClient()?->only(['id', 'client_name', 'client_lname', 'client_rfc']),
        ]);
    }

    /**
     * El nombre debe generar una ruta válida y no repetirse dentro de la misma carpeta.
     */
    private function validatedName(Request $request, Folder $parent, ?Folder $current = null): string
    {
        $validated = $request->validate([
            'folder_name' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, Closure $fail) use ($parent, $current) {
                    $slug = Str::slug((string) $value);

                    if ($slug === '') {
                        $fail('El nombre debe incluir letras o números.');
                        return;
                    }

                    $exists = Folder::where('parent_id', $parent->id)
                        ->where('slug', $slug)
                        ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                        ->exists();

                    if ($exists) {
                        $fail('Ya existe una carpeta con ese nombre aquí.');
                    }
                },
            ],
        ]);

        return Str::squish($validated['folder_name']);
    }
}
