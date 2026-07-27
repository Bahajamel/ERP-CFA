<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onglet « Contrat » complet du dossier (tour de contrôle) : termes du contrat,
 * calendrier & rémunération, rémunération par année, données financières (NPEC /
 * engagement OPCO), reste à charge entreprise, calendrier de financement
 * pluriannuel et frais annexes.
 *
 * Toutes ces données alimentent le CERFA 10103*14, la convention de formation
 * par apprentissage, le calcul de rémunération, le financement OPCO et le reste
 * à charge / la facturation. Migration additive et douce — les anciens dossiers
 * restent valides (toutes les colonnes sont nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            // --- Termes du contrat ---
            $table->string('nature_contrat')->nullable();
            $table->boolean('derogation')->nullable();
            $table->unsignedTinyInteger('duree_hebdo_minutes')->nullable();
            $table->decimal('avantage_repas', 8, 2)->nullable();
            $table->decimal('avantage_logement', 8, 2)->nullable();
            $table->boolean('autres_avantages')->nullable();
            $table->string('autres_avantages_detail')->nullable();
            $table->boolean('travail_dangereux')->nullable();
            $table->text('missions')->nullable();

            // --- Calendrier & rémunération ---
            $table->date('date_debut_contrat')->nullable();
            $table->date('date_fin_contrat')->nullable();
            $table->date('date_fin_periode_essai')->nullable();
            $table->date('date_conclusion')->nullable();
            $table->date('date_debut_formation_pratique')->nullable();
            $table->boolean('smc')->nullable();
            $table->decimal('pourcentage_smic', 5, 2)->nullable();

            // Rémunération par année d'exécution (évolutif : Année 1, 2, 3…).
            // [{annee, date_debut, date_fin, pourcentage, base}]
            $table->json('remuneration_annuelle')->nullable();

            // --- Données financières / financement OPCO ---
            $table->decimal('npec_annuel', 10, 2)->nullable();
            $table->decimal('npec_journalier', 10, 2)->nullable();
            $table->unsignedSmallInteger('nombre_jours_contrat')->nullable();
            $table->decimal('engagement_opco_total', 10, 2)->nullable();

            // --- Montant du reste à charge pour l'entreprise ---
            $table->decimal('reste_a_charge_montant', 10, 2)->nullable();
            $table->decimal('participation_obligatoire', 10, 2)->nullable();
            $table->decimal('participation_cfa', 10, 2)->nullable();
            $table->decimal('net_a_payer', 10, 2)->nullable();

            // Calendrier de financement pluriannuel :
            // [{annee, formation, financement, geste_commercial, reste_a_charge}]
            $table->json('calendrier_financement')->nullable();

            // --- Frais annexes ---
            $table->boolean('frais_hebergement')->nullable();
            $table->boolean('frais_restauration')->nullable();
            $table->boolean('frais_equipement')->nullable();
            $table->boolean('frais_mobilite')->nullable();
            $table->string('type_equipement')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'nature_contrat', 'derogation', 'duree_hebdo_minutes', 'avantage_repas',
                'avantage_logement', 'autres_avantages', 'autres_avantages_detail',
                'travail_dangereux', 'missions',
                'date_debut_contrat', 'date_fin_contrat', 'date_fin_periode_essai',
                'date_conclusion', 'date_debut_formation_pratique', 'smc', 'pourcentage_smic',
                'remuneration_annuelle',
                'npec_annuel', 'npec_journalier', 'nombre_jours_contrat', 'engagement_opco_total',
                'reste_a_charge_montant', 'participation_obligatoire', 'participation_cfa', 'net_a_payer',
                'calendrier_financement',
                'frais_hebergement', 'frais_restauration', 'frais_equipement', 'frais_mobilite', 'type_equipement',
            ]);
        });
    }
};
