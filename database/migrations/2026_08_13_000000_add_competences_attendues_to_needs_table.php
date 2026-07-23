<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compétences recherchées par l'entreprise pour l'offre. Saisies par l'entreprise
 * elle-même sur le formulaire public (fiche besoin), une par ligne, elles
 * alimentent la section « Compétences attendues » de la fiche besoin PDF.
 *
 * Distinct des « prérequis » (missions décrites en prose) : ici, la liste des
 * compétences visées, que le commercial peut compléter avant de générer la fiche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table): void {
            $table->text('competences_attendues')->nullable()->after('prerequis');
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table): void {
            $table->dropColumn('competences_attendues');
        });
    }
};
