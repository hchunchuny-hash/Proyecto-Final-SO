<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('algoritmo', 20);
            $table->unsignedSmallInteger('quantum')->nullable();
            $table->json('procesos');
            $table->decimal('espera_promedio', 8, 2);
            $table->decimal('retorno_promedio', 8, 2);
            $table->unsignedInteger('cambios_contexto');
            $table->json('resultado');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulaciones');
    }
};
