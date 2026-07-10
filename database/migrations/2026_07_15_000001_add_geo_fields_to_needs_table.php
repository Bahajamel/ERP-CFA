<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Géolocalisation d'un besoin + rayon de recherche (km). Le point GPS (renseigné
 * par l'autocomplétion Base Adresse Nationale à partir de la localisation) sert
 * de centre au cercle de rayon affiché sur la carte du formulaire de besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('localisation');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('rayon_km')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'rayon_km']);
        });
    }
};
