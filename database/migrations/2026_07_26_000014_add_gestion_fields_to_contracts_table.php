<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onglet « Gestion » du dossier : marqueurs de gestion (non-conformité,
 * annulation logique) et réglages du dossier (relances, facturation OPCO).
 *
 * L'annulation est VOLONTAIREMENT logique (annule_at) et non une suppression
 * physique : le dossier reste consultable mais sort du cycle de traitement.
 * La non-conformité est un marqueur, pas un état de la machine à états (qui
 * n'a pas d'état dédié). Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->boolean('non_conforme')->nullable();
            $table->text('non_conforme_motif')->nullable();
            $table->timestamp('non_conforme_at')->nullable();
            $table->timestamp('annule_at')->nullable();
            $table->text('annulation_motif')->nullable();
            $table->boolean('relances_activees')->default(true);
            $table->boolean('facturation_opco')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'non_conforme', 'non_conforme_motif', 'non_conforme_at',
                'annule_at', 'annulation_motif', 'relances_activees', 'facturation_opco',
            ]);
        });
    }
};
