<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            // PIN de verificación en dos pasos usado al registrar el número
            // (encriptado con cast 'encrypted', igual que access_token).
            $table->text('two_step_pin')->nullable()->after('access_token');
            $table->string('verified_name')->nullable()->after('phone_e164');
            $table->string('quality_rating', 32)->nullable()->after('verified_name');
            $table->timestamp('connected_at')->nullable()->after('connection_status');
            // Última verificación de salud (pilo:verificar-conexiones).
            $table->timestamp('last_checked_at')->nullable()->after('connected_at');
        });

        // Bitácora del Embedded Signup para depurar altas fallidas: paso
        // alcanzado y error de Meta (código + mensaje), nunca tokens.
        Schema::create('onboarding_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->string('status'); // ok | skipped | error
            $table->string('meta_error_code')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_logs');

        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn(['two_step_pin', 'verified_name', 'quality_rating', 'connected_at', 'last_checked_at']);
        });
    }
};
