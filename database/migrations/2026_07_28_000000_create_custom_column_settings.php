<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personnalisation « façon Monday » des colonnes de BASE (natives) d'une entité :
 * un CFA renomme une colonne existante (ex. « Référent » → « Chargé de compte »)
 * et fixe sa largeur (au sélecteur ou au glisser-déposer souris), sans toucher au
 * code ni aux autres CFA.
 *
 * On ne stocke QUE les surcharges (label / largeur). L'absence de ligne = colonne
 * d'origine inchangée. Les colonnes AJOUTÉES, elles, restent gérées par
 * custom_field_definitions. Cloisonné par CFA (BelongsToOrganisation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_column_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            // Entité métier concernée (ex. « candidate »).
            $table->string('entity');
            // Clé technique de la colonne native (ex. « commercial.name »).
            $table->string('column_key');
            // Surcharge du libellé (null = libellé d'origine).
            $table->string('label')->nullable();
            // Largeur imposée en pixels (null = largeur automatique).
            $table->unsignedInteger('width')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'entity', 'column_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_column_settings');
    }
};
