<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit le contrat avec les informations collectées à la création du dossier
 * (assistant en 4 étapes), destinées au futur CERFA et à la convention :
 *  - type de contrat (apprentissage / professionnalisation) ;
 *  - découpage pédagogique de la formation (organismes, modalité de suivi,
 *    heures e-learning / classe virtuelle) pour le contrôle « majorité à
 *    distance » (minoration OPCO, décret du 1er juillet 2025) ;
 *  - reste à charge à 0 € automatique (hors participation obligatoire) ;
 *  - durée nécessaire à l'obtention du diplôme et année d'entrée dans le cycle.
 *
 * Migration additive et douce : aucune donnée existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('type_contrat')->default('apprentissage')->after('formation_id');
            $table->unsignedSmallInteger('nombre_organismes_formation')->nullable()->after('duree_formation_heures');
            $table->string('modalite_suivi')->nullable()->after('nombre_organismes_formation');
            $table->unsignedSmallInteger('heures_elearning')->nullable()->after('modalite_suivi');
            $table->unsignedSmallInteger('heures_classe_virtuelle')->nullable()->after('heures_elearning');
            $table->boolean('reste_a_charge_zero')->default(false)->after('cout_formation');
            $table->string('duree_diplome')->nullable()->after('reste_a_charge_zero');
            $table->string('annee_cycle')->nullable()->after('duree_diplome');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'type_contrat',
                'nombre_organismes_formation',
                'modalite_suivi',
                'heures_elearning',
                'heures_classe_virtuelle',
                'reste_a_charge_zero',
                'duree_diplome',
                'annee_cycle',
            ]);
        });
    }
};
