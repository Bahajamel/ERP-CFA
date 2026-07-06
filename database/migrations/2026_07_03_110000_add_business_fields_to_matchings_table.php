<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs métier complémentaires du matching (EPIC-05) :
 *  - refusal_reason : motif de refus (obligatoire à la transition vers un statut « Refusé ») ;
 *  - next_action_at : date de la prochaine action commerciale (relance, suivi) ;
 *  - notes          : notes internes du commercial.
 * Migration additive : aucune colonne existante n'est renommée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchings', function (Blueprint $table) {
            $table->text('refusal_reason')->nullable()->after('retour_entreprise');
            $table->date('next_action_at')->nullable()->after('refusal_reason');
            $table->text('notes')->nullable()->after('next_action_at');
        });
    }

    public function down(): void
    {
        Schema::table('matchings', function (Blueprint $table) {
            $table->dropColumn(['refusal_reason', 'next_action_at', 'notes']);
        });
    }
};
