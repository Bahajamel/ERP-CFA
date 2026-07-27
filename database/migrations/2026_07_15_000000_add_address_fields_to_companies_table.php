<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresse structurée + géolocalisation pour les entreprises, alignée sur les
 * candidats. La colonne texte `adresse` existante est conservée (voie / libellé
 * complet) ; on ajoute code postal, ville, pays et les coordonnées GPS
 * renseignées par l'autocomplétion Base Adresse Nationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('code_postal')->nullable()->after('adresse');
            $table->string('ville')->nullable()->after('code_postal');
            $table->string('pays')->default('France')->after('ville');
            $table->decimal('latitude', 10, 7)->nullable()->after('pays');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['code_postal', 'ville', 'pays', 'latitude', 'longitude']);
        });
    }
};
