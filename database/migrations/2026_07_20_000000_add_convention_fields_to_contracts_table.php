<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit le contrat pour la génération intelligente de la convention de
 * formation par apprentissage (Annexe n°2) et du CERFA :
 *  - lieu principal de formation structuré (adresse + CP + ville + GPS),
 *    alimenté par l'adresse intelligente (BAN) — saisie manuelle possible ;
 *  - durée totale de formation en heures (Article 2 de la convention) ;
 *  - coût de la formation, net de taxe (Article 4 — prix de la prestation).
 *
 * Migration additive et douce : aucune donnée existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('lieu_formation_code_postal')->nullable()->after('lieu_formation');
            $table->string('lieu_formation_ville')->nullable()->after('lieu_formation_code_postal');
            $table->decimal('lieu_formation_latitude', 10, 7)->nullable()->after('lieu_formation_ville');
            $table->decimal('lieu_formation_longitude', 10, 7)->nullable()->after('lieu_formation_latitude');
            $table->unsignedSmallInteger('duree_formation_heures')->nullable()->after('lieu_formation_longitude');
            $table->decimal('cout_formation', 10, 2)->nullable()->after('duree_formation_heures');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'lieu_formation_code_postal',
                'lieu_formation_ville',
                'lieu_formation_latitude',
                'lieu_formation_longitude',
                'duree_formation_heures',
                'cout_formation',
            ]);
        });
    }
};
