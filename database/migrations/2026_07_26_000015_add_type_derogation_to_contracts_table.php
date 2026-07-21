<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détail de la dérogation du contrat : quand `derogation` est vraie, le CERFA
 * attend un CODE précisant la nature de la dérogation (âge, durée, cumul…).
 * Le simple booléen ne suffisait pas à remplir la case « Type de dérogation »
 * du formulaire officiel. Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('type_derogation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('type_derogation');
        });
    }
};
