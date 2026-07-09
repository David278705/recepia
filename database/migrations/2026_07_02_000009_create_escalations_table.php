<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Denormalizado desde conversations.business_id: scoping e índices por negocio.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->enum('reason', ['no_sabe', 'cliente_lo_pidio', 'molestia', 'keyword']);
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
