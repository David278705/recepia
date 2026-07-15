<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Qué puede hacer el recepcionista de este negocio además de
            // responder: agendar (citas), pedidos, cotizar. NULL = ['agendar']
            // (compatibilidad con los negocios existentes).
            $table->json('capabilities')->nullable()->after('daily_message_limit');
        });

        // Solicitudes capturadas por el bot que no son citas con hora:
        // pedidos y solicitudes de cotización, con payload libre por tipo.
        Schema::create('customer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // pedido | cotizacion
            $table->json('payload');
            $table->string('status')->default('nueva'); // nueva | atendida | cerrada
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_requests');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};
