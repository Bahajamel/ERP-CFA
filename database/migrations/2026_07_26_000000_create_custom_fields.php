<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couche de personnalisation « façon Monday », par CFA (multi-tenant).
 *
 * - custom_field_definitions : les colonnes personnalisées qu'un CFA définit
 *   pour une entité (candidat…), avec leur type et leur visibilité. Isolées par
 *   organisation (BelongsToOrganisation) : un CFA ne voit jamais celles d'un autre.
 * - candidates.custom_fields : les VALEURS de ces champs, en JSONB { clé: valeur }.
 *   Additif — n'affecte pas le cœur métier (matching, contrats, OPCO, permissions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('entity');              // ex. « candidate »
            $table->string('key');                 // clé technique (slug), stable
            $table->string('label');               // libellé affiché
            $table->string('type')->default('text'); // CustomFieldType
            $table->jsonb('config')->nullable();   // options (listes déroulantes…)
            $table->boolean('visible_table')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();

            // Une clé unique par CFA et par entité (deux CFA peuvent réutiliser la même clé).
            $table->unique(['organisation_id', 'entity', 'key']);
            $table->index(['organisation_id', 'entity']);
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->jsonb('custom_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn('custom_fields');
        });

        Schema::dropIfExists('custom_field_definitions');
    }
};
