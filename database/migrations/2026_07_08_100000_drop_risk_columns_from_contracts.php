<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retrait de la fonctionnalité « Risque de rupture » : suppression des colonnes
 * de score portées par les contrats. Gardé par hasColumn pour être sans effet
 * sur une base neuve (où les colonnes n'ont jamais été créées).
 */
return new class extends Migration
{
    public function up(): void
    {
        $colonnes = array_values(array_filter(
            ['risk_score', 'risk_level', 'risk_factors', 'risk_evaluated_at'],
            fn (string $col): bool => Schema::hasColumn('contracts', $col),
        ));

        if ($colonnes !== []) {
            Schema::table('contracts', function (Blueprint $table) use ($colonnes) {
                $table->dropColumn($colonnes);
            });
        }
    }

    public function down(): void
    {
        // Fonctionnalité retirée : pas de restauration.
    }
};
