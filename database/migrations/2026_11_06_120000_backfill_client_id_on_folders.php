<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las subcarpetas creadas desde el panel se guardaban sin client_id y no aceptaban archivos.
     * Hereda el cliente de la carpeta padre; las raíces se enlazan por su nombre (el RFC).
     */
    public function up(): void
    {
        DB::table('folders')->whereNull('client_id')->whereNull('parent_id')->orderBy('id')->get()
            ->each(function ($root) {
                $clientId = DB::table('clients')->where('client_rfc', strtolower($root->folder_name))->value('id');

                if ($clientId) {
                    DB::table('folders')->where('id', $root->id)->update(['client_id' => $clientId]);
                }
            });

        // Un nivel por pasada: termina cuando ya no queda ninguna carpeta por completar.
        do {
            $updated = 0;

            DB::table('folders as child')
                ->join('folders as parent', 'parent.id', '=', 'child.parent_id')
                ->whereNull('child.client_id')
                ->whereNotNull('parent.client_id')
                ->select('child.id', 'parent.client_id')
                ->get()
                ->each(function ($row) use (&$updated) {
                    $updated += DB::table('folders')->where('id', $row->id)->update(['client_id' => $row->client_id]);
                });
        } while ($updated > 0);
    }

    public function down(): void
    {
        // Sin reversa: no se puede distinguir qué carpetas venían vacías.
    }
};
