<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complète l'identité légale de l'entreprise, nécessaire au CERFA et à la
 * convention de formation par apprentissage :
 *  - SIREN (unité légale, 9 chiffres) et SIRET de l'établissement d'exécution
 *    du contrat (quand il diffère du siège) ;
 *  - forme juridique et ville du RCS ;
 *  - numéro de voirie du siège et complément d'adresse (adresse structurée).
 *
 * La colonne `siret` existante reste le SIRET principal (siège) et n'est pas
 * touchée. Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('siren')->nullable()->after('siret');
            $table->string('siret_etablissement')->nullable()->after('siren');
            $table->string('forme_juridique')->nullable()->after('nom_commercial');
            $table->string('ville_rcs')->nullable()->after('forme_juridique');
            $table->string('numero_siege')->nullable()->after('adresse');
            $table->string('complement_adresse')->nullable()->after('numero_siege');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'siren',
                'siret_etablissement',
                'forme_juridique',
                'ville_rcs',
                'numero_siege',
                'complement_adresse',
            ]);
        });
    }
};
