<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // Momento en que el chat se verificó para el cliente en client_id. Null = sin verificar.
            $table->timestamp('verified_at')->nullable()->after('is_client');
            // Cómo se verificó: 'phone' (número registrado) u 'otp' (código enviado al titular).
            $table->string('verification_method', 20)->nullable()->after('verified_at');
            $table->index('wa_id');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex(['wa_id']);
            $table->dropColumn(['verified_at', 'verification_method']);
        });
    }
};
