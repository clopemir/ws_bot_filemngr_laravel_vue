<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Para quitar los índices únicos, necesitas saber cómo se llaman.
            // Si Laravel los creó automáticamente, el nombre suele ser: table_column_unique

            $table->dropUnique('clients_client_phone_unique');
            $table->dropUnique('clients_client_mail_unique');
            $table->dropUnique('clients_wa_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('client_phone');
            $table->unique('client_mail');
            $table->unique('wa_id');
        });
    }
};
