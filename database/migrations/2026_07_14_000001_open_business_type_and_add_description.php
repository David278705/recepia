<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // De enum cerrado a texto libre: el tipo se interpola en el prompt
            // del agente, así que cualquier vertical funciona sin migrar.
            $table->string('type', 100)->default('otro')->change();

            // Descripción libre del negocio; se inyecta al prompt del bot para
            // darle contexto real más allá de la etiqueta de tipo.
            $table->text('description')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
