<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode contractuel de l'apprentissage (CERFA rubrique en tête, codes 1-4 :
 * CDD, CDI, entreprise de travail temporaire, activités saisonnières à deux
 * employeurs). Cette case du CERFA n'était jusqu'ici ni saisie ni remplie.
 * Distinct de `nature_contrat` (type de contrat/avenant, codes 11-38) et de
 * `type_contrat` (apprentissage / professionnalisation). Migration douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('mode_contractuel')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('mode_contractuel');
        });
    }
};
