<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // 'tarjeta' se renueva sola contra la fuente de pago; con
            // 'transferencia' el dueño paga manualmente cada mes (botón
            // Bancolombia) dentro del periodo de gracia.
            $table->enum('payment_method', ['tarjeta', 'transferencia'])->default('tarjeta')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('payment_method'));
    }
};
