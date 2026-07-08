<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F-16 — Salaire mensuel brut de l'apprenti, contrôlé contre le
     * plancher légal (grille % du SMIC, art. D6222-26). Nullable :
     * les contrats existants restent valides sans saisie.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('salaire_mensuel_brut', 8, 2)->nullable()->after('lieu_formation');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('salaire_mensuel_brut');
        });
    }
};
