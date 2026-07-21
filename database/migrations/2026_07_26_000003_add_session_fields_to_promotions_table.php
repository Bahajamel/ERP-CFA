<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit la classe / promotion pour en faire une véritable SESSION de
 * formation (façon « intake » mensuel : « TP EPR 12 MOIS JUIN 2025 »), qui sert
 * de MODÈLE au contrat : nom libre, responsable pédagogique, lieu de formation
 * structuré, et la configuration pédagogique + financière (type de contrat,
 * modalité de suivi, heures e-learning / classe virtuelle, durée, reste à
 * charge, frais annexes).
 *
 * La logique de cohorte existante (formation + niveau via `libelle`, année
 * scolaire) est CONSERVÉE : ces champs sont additifs. Un contrat créé pour une
 * promotion héritera de ces valeurs par défaut (le contrat garde sa propre copie
 * pour figer le CERFA).
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            // Identité de la session
            $table->string('nom')->nullable()->after('libelle');
            $table->foreignId('responsable_id')->nullable()->after('nom')
                ->constrained('users')->nullOnDelete();

            // Lieu de formation structuré (adresse intelligente BAN)
            $table->string('lieu_formation')->nullable()->after('date_fin');
            $table->string('lieu_formation_numero')->nullable()->after('lieu_formation');
            $table->string('lieu_formation_complement')->nullable()->after('lieu_formation_numero');
            $table->string('lieu_formation_code_postal')->nullable()->after('lieu_formation_complement');
            $table->string('lieu_formation_ville')->nullable()->after('lieu_formation_code_postal');
            $table->string('lieu_formation_pays')->default('France')->after('lieu_formation_ville');
            $table->decimal('lieu_formation_latitude', 10, 7)->nullable()->after('lieu_formation_pays');
            $table->decimal('lieu_formation_longitude', 10, 7)->nullable()->after('lieu_formation_latitude');

            // Configuration pédagogique / financière (modèle pour le contrat)
            $table->string('type_contrat')->default('apprentissage')->after('lieu_formation_longitude');
            $table->string('modalite_suivi')->nullable()->after('type_contrat');
            $table->unsignedSmallInteger('duree_formation_heures')->nullable()->after('modalite_suivi');
            $table->unsignedSmallInteger('heures_elearning')->nullable()->after('duree_formation_heures');
            $table->unsignedSmallInteger('heures_classe_virtuelle')->nullable()->after('heures_elearning');
            $table->boolean('reste_a_charge_zero')->default(false)->after('heures_classe_virtuelle');

            // Frais annexes (finançables OPCO)
            $table->boolean('frais_hebergement')->default(false)->after('reste_a_charge_zero');
            $table->boolean('frais_restauration')->default(false)->after('frais_hebergement');
            $table->boolean('frais_equipement')->default(false)->after('frais_restauration');
            $table->string('type_equipement')->nullable()->after('frais_equipement');
            $table->boolean('frais_mobilite')->default(false)->after('type_equipement');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsable_id');
            $table->dropColumn([
                'nom',
                'lieu_formation',
                'lieu_formation_numero',
                'lieu_formation_complement',
                'lieu_formation_code_postal',
                'lieu_formation_ville',
                'lieu_formation_pays',
                'lieu_formation_latitude',
                'lieu_formation_longitude',
                'type_contrat',
                'modalite_suivi',
                'duree_formation_heures',
                'heures_elearning',
                'heures_classe_virtuelle',
                'reste_a_charge_zero',
                'frais_hebergement',
                'frais_restauration',
                'frais_equipement',
                'type_equipement',
                'frais_mobilite',
            ]);
        });
    }
};
