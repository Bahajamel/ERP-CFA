<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structure l'adresse du candidat : on garde `adresse` (voie / libellé complet)
 * et on ajoute code postal, ville et pays — remplis par l'autocomplétion Base
 * Adresse Nationale ou saisis à la main. Additif, aucune donnée existante perdue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('code_postal')->nullable()->after('adresse');
            $table->string('ville')->nullable()->after('code_postal');
            $table->string('pays')->nullable()->default('France')->after('ville');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['code_postal', 'ville', 'pays']);
        });
    }
};
