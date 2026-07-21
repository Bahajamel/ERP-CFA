<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identité du lieu principal de réalisation de la formation lorsqu'il est
 * distinct du CFA responsable : dénomination, UAI et SIRET. Le SIRET sert à la
 * vérification de l'habilitation à former (décret n°2024-631) : le CERFA ne
 * portait jusqu'ici que l'adresse du lieu, pas son identité. Migration douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('lieu_formation_denomination')->nullable();
            $table->string('lieu_formation_uai')->nullable();
            $table->string('lieu_formation_siret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['lieu_formation_denomination', 'lieu_formation_uai', 'lieu_formation_siret']);
        });
    }
};
