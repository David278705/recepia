<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['barberia', 'clinica', 'restaurante', 'otro'])->default('otro');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('timezone')->default('America/Bogota');
            $table->enum('status', ['piloto', 'activo', 'pausado'])->default('piloto');

            // Configuración del bot
            $table->enum('tone', ['formal', 'cercano'])->default('cercano');
            $table->text('extra_instructions')->nullable(); // instrucciones libres del dueño para el bot

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
