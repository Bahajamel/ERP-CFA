<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secteur d'activité Privé/Public de l'entreprise (onglet « Entreprise » du
 * dossier). Distinct de la colonne `secteur` qui, elle, porte le secteur NAF
 * (« Activités financières et d'assurance », rempli depuis le SIRET et réutilisé
 * dans la table Entreprises, les filtres et l'export). On ne veut donc PAS
 * écraser le NAF avec Privé/Public : d'où cette colonne dédiée.
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('secteur_type')->nullable()->after('secteur');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('secteur_type');
        });
    }
};
