<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Cara al cliente final, el agente se presenta como asistente DEL
            // NEGOCIO. Solo si el dueño lo activa se presenta como "Pilo, el
            // asistente de {negocio}" — la marca es para el dueño que paga;
            // el cliente final le pertenece al negocio.
            $table->boolean('show_brand')->default(false)->after('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('show_brand');
        });
    }
};
