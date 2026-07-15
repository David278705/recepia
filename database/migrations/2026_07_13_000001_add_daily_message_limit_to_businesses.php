<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Máximo de respuestas del bot por conversación en una ventana
            // móvil de 24 h; al alcanzarlo la conversación se escala al dueño.
            $table->unsignedInteger('daily_message_limit')->default(20)->after('monthly_price_cents');
        });

        // De enum a string: permite nuevos motivos (limite_mensajes) sin
        // alterar el enum en cada ocasión.
        Schema::table('escalations', function (Blueprint $table) {
            $table->string('reason')->change();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('daily_message_limit');
        });
    }
};
