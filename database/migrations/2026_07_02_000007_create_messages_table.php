<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Denormalizado desde conversations.business_id: permite scoping e
            // indexado directo por negocio sin necesidad de join.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->enum('direction', ['in', 'out']);
            $table->enum('origin', ['cliente', 'bot', 'dueno_app', 'dueno_panel']);
            $table->enum('type', ['text', 'image', 'audio', 'video', 'document', 'location', 'interactive', 'other'])->default('text');
            $table->text('content')->nullable();
            $table->string('wamid')->nullable()->unique(); // id de Meta, para idempotencia
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'read', 'failed'])->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->decimal('estimated_cost', 8, 4)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
