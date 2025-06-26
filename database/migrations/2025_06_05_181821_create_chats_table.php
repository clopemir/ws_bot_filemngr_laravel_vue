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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('wa_id');
            $table->string('user_name');
            $table->string('user_phone');
            $table->string('client_rfc')->nullable();
            $table->foreignId('client_id')->nullable()->constrained();
            $table->json('context');
            $table->string('user_intention');
            $table->boolean('is_client')->default(false);
            $table->string('action');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
