<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week'); // 0 (domingo) - 6 (sábado)
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['business_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
