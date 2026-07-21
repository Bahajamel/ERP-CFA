<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liens et champs de formation gérés depuis l'onglet « Étudiant » du dossier :
 *  - promotion (session) rattachée au contrat ;
 *  - responsable pédagogique (distinct du responsable interne du dossier) ;
 *  - modèle de convention retenu ;
 *  - lieu de réalisation de la formation complété (numéro, complément, pays)
 *    pour l'adresse structurée du CERFA / de la convention.
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->after('formation_id')
                ->constrained('promotions')->nullOnDelete();
            $table->foreignId('responsable_pedagogique_id')->nullable()->after('responsable_id')
                ->constrained('users')->nullOnDelete();
            $table->string('convention_modele')->nullable()->after('responsable_pedagogique_id');
            $table->string('lieu_formation_numero')->nullable()->after('lieu_formation');
            $table->string('lieu_formation_complement')->nullable()->after('lieu_formation_numero');
            $table->string('lieu_formation_pays')->nullable()->default('France')->after('lieu_formation_ville');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropConstrainedForeignId('responsable_pedagogique_id');
            $table->dropColumn(['convention_modele', 'lieu_formation_numero', 'lieu_formation_complement', 'lieu_formation_pays']);
        });
    }
};
