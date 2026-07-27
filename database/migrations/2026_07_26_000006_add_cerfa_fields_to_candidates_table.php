<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État civil détaillé, situation sociale/administrative et parcours d'études de
 * l'apprenti — collectés dans l'onglet « Étudiant » du dossier et nécessaires au
 * CERFA 10103*14 et à la convention.
 *
 * Le NIR (num_secu) est chiffré au repos (cast « encrypted » sur le modèle) : il
 * est stocké en TEXT pour contenir le chiffré. Donnée sensible — cf. politique
 * des pièces sensibles.
 *
 * Toutes les colonnes sont nullables : les anciens dossiers restent valides et se
 * complètent progressivement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            // État civil
            $table->string('sexe')->nullable()->after('prenom');
            $table->boolean('emancipe')->nullable()->after('nationalite');
            $table->boolean('ne_en_france')->nullable()->after('emancipe');
            $table->string('departement_naissance')->nullable()->after('ne_en_france');
            $table->text('num_secu')->nullable()->after('departement_naissance');

            // Situation sociale / administrative
            $table->boolean('rqth')->nullable();
            $table->boolean('aeeh_pch_pps')->nullable();
            $table->boolean('boe')->nullable();
            $table->string('regime_social')->nullable();
            $table->boolean('sportif_haut_niveau')->nullable();
            $table->string('situation_avant_contrat')->nullable();
            $table->boolean('projet_creation_entreprise')->nullable();
            $table->boolean('formation_initiale_precedente')->nullable();

            // Études
            $table->string('niveau_diplome_max')->nullable();
            $table->string('diplome_max')->nullable();
            $table->string('niveau_dernier_diplome_prepare')->nullable();
            $table->string('dernier_diplome_prepare')->nullable();
            $table->string('intitule_dernier_diplome')->nullable();
            $table->string('derniere_classe_suivie')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn([
                'sexe', 'emancipe', 'ne_en_france', 'departement_naissance', 'num_secu',
                'rqth', 'aeeh_pch_pps', 'boe', 'regime_social', 'sportif_haut_niveau',
                'situation_avant_contrat', 'projet_creation_entreprise', 'formation_initiale_precedente',
                'niveau_diplome_max', 'diplome_max', 'niveau_dernier_diplome_prepare',
                'dernier_diplome_prepare', 'intitule_dernier_diplome', 'derniere_classe_suivie',
            ]);
        });
    }
};
