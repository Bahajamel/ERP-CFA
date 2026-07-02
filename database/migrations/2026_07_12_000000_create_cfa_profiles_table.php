<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil du CFA (singleton) : identité, référents et préférences de génération
 * des livrables. Alimente l'intégration LivretRS (étapes 1-3 & 5 du logiciel).
 * Le logo, la signature et le cachet sont gérés via la media library.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfa_profiles', function (Blueprint $table) {
            $table->id();

            // Identité (étape 1 LivretRS)
            $table->string('nom')->default('CFA');
            $table->string('raison_sociale')->nullable();
            $table->string('siren')->nullable();
            $table->string('siret')->nullable();
            $table->string('naf')->nullable();
            $table->string('nda')->nullable();            // déclaration d'activité
            $table->string('numero_uai')->nullable();
            $table->string('adresse')->nullable();
            $table->string('code_postal')->nullable();
            $table->string('ville')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Référents (obligatoires pour plusieurs livrables L6231-2 / Qualiopi)
            $table->string('representant_nom')->nullable();
            $table->string('representant_prenom')->nullable();
            $table->string('representant_fonction')->nullable();
            $table->string('referent_pedagogique_nom')->nullable();
            $table->string('referent_pedagogique_prenom')->nullable();
            $table->string('referent_handicap_nom')->nullable();
            $table->string('referent_handicap_prenom')->nullable();
            $table->string('referent_mobilite_nom')->nullable();
            $table->string('referent_mobilite_prenom')->nullable();
            $table->string('dpo_nom')->nullable();
            $table->string('dpo_prenom')->nullable();

            // Préférences de génération (étape 5 LivretRS)
            $table->string('theme_defaut')->default('institutionnel');
            $table->string('format_defaut')->default('pdf'); // pdf | pdf_docx
            $table->boolean('verifier_rncp')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfa_profiles');
    }
};
