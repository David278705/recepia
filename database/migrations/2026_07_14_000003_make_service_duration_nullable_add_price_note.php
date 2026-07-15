<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // NULL = servicio informativo: el bot lo menciona y cotiza, pero
            // no se agenda (el motor de slots lo ignora).
            $table->unsignedInteger('duration_minutes')->nullable()->change();

            // Aclaración de precio no fijo: "desde", "según tamaño", etc.
            $table->string('price_note', 100)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('price_note');
        });
    }
};
