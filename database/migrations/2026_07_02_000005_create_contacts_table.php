<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('wa_id'); // número del cliente final (WhatsApp)
            $table->string('name')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
