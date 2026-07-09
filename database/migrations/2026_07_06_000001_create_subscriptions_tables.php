<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Precio mensual de la suscripción en centavos de COP, definido por
            // el super admin al crear el negocio. NULL = sin cobro (piloto).
            $table->unsignedBigInteger('monthly_price_cents')->nullable()->after('status');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pendiente', 'activa', 'vencida', 'cancelada'])->default('pendiente');
            $table->unsignedBigInteger('price_cents');
            $table->string('currency', 3)->default('COP');
            // Fuente de pago recurrente en Wompi (tarjeta tokenizada).
            $table->string('wompi_payment_source_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_period_ends_at']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('wompi_transaction_id')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('COP');
            // Estados de transacción de Wompi: PENDING / APPROVED / DECLINED / VOIDED / ERROR
            $table->string('status')->default('PENDING');
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('monthly_price_cents'));
    }
};
