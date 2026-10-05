<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices únicos que se eliminan para permitir que varios clientes (RFC) compartan teléfono o correo.
     */
    private const INDEXES = [
        'clients_client_phone_unique' => 'client_phone',
        'clients_client_mail_unique' => 'client_mail',
        'clients_wa_id_unique' => 'wa_id',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotente: en producción estos índices ya se habían eliminado a mano.
        foreach (array_keys(self::INDEXES) as $index) {
            if (Schema::hasIndex('clients', $index)) {
                Schema::table('clients', fn (Blueprint $table) => $table->dropUnique($index));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::INDEXES as $index => $column) {
            if (!Schema::hasIndex('clients', $index)) {
                Schema::table('clients', fn (Blueprint $table) => $table->unique($column, $index));
            }
        }
    }
};
