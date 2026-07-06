<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplification de la disponibilité candidat : une seule date « disponible à
 * partir du » (champ natif simple demandé côté métier). La table
 * `candidate_availabilities` est conservée (données existantes), mais n'est
 * plus saisie depuis le formulaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->date('date_disponibilite')->nullable()->after('disponibilite');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn('date_disponibilite');
        });
    }
};
