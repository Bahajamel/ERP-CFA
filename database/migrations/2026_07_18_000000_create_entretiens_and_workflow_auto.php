<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Section « Entretiens » + workflow automatique du cycle apprenant.
 *
 * 1. Nouvelle table `entretiens` : planification et suivi des entretiens
 *    candidats (date, créneau, mode, compte-rendu, décision).
 * 2. `matchings.need_id` devient nullable : dès qu'un candidat est accepté,
 *    un matching « En recherche » est ouvert automatiquement — l'entreprise
 *    (le besoin) n'est rattachée que plus tard.
 * 3. Nouveau statut candidat initial « Entretien à planifier » : le statut
 *    « Entretien prévu » est désormais réservé aux candidats ayant un
 *    entretien réellement planifié (date + heures).
 *
 * Adaptation douce des données : les candidats « entretien_prevu » existants
 * repassent à « entretien_a_planifier » (aucun entretien n'existe encore en
 * base — le statut reflète la réalité). Les décisions finales sont conservées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entretiens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_entretien')->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->string('mode')->default('presentiel');
            $table->string('lien_visio')->nullable();
            $table->string('statut')->default('a_planifier');
            $table->string('resultat')->nullable();
            $table->text('compte_rendu')->nullable();
            $table->text('note_interne')->nullable();
            $table->timestamps();

            $table->index(['date_entretien', 'statut']);
        });

        // Le matching peut exister avant que l'entreprise soit identifiée.
        Schema::table('matchings', function (Blueprint $table): void {
            $table->foreignId('need_id')->nullable()->change();
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->string('statut')->default('entretien_a_planifier')->change();
        });

        $this->adapterDonnees();
    }

    /**
     * Aucun entretien n'existe encore : les candidats « entretien_prevu »
     * redémarrent à « entretien_a_planifier » (l'équipe planifie ensuite
     * l'entretien depuis la nouvelle section). One-way assumée.
     */
    private function adapterDonnees(): void
    {
        DB::table('candidates')
            ->where('statut', 'entretien_prevu')
            ->update(['statut' => 'entretien_a_planifier']);
    }

    public function down(): void
    {
        DB::table('candidates')
            ->where('statut', 'entretien_a_planifier')
            ->update(['statut' => 'entretien_prevu']);

        Schema::table('candidates', function (Blueprint $table): void {
            $table->string('statut')->default('entretien_prevu')->change();
        });

        // Les matchings sans entreprise n'existent pas dans l'ancien schéma.
        DB::table('matchings')->whereNull('need_id')->delete();

        Schema::table('matchings', function (Blueprint $table): void {
            $table->foreignId('need_id')->nullable(false)->change();
        });

        Schema::dropIfExists('entretiens');
    }
};
