<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Tableaux personnalisés « façon Monday » : un CFA crée ses propres
 * tables (nom + colonnes) et y saisit ses lignes, en plus des modules métier.
 *
 * On réutilise custom_field_definitions pour les colonnes : quand elles portent
 * un custom_table_id, ce sont les colonnes d'un tableau personnalisé (et non
 * d'une entité métier). Les lignes vivent dans custom_records.data (JSONB).
 * Isolé par CFA (BelongsToOrganisation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('custom_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('custom_table_id')->constrained()->cascadeOnDelete();
            $table->jsonb('data')->nullable();
            $table->timestamps();
            $table->index(['custom_table_id']);
        });

        Schema::table('custom_field_definitions', function (Blueprint $table): void {
            // Colonne rattachée à un tableau personnalisé (null = colonne d'une
            // entité métier existante, ex. candidate).
            $table->foreignId('custom_table_id')->nullable()->after('entity')->constrained()->cascadeOnDelete();

            // L'unicité (org, entity, key) empêchait deux tableaux d'avoir une
            // même clé de colonne : l'unicité est désormais garantie en code
            // (par CFA + entité + tableau). On garde un simple index.
            $table->dropUnique(['organisation_id', 'entity', 'key']);
            $table->index(['organisation_id', 'entity', 'custom_table_id']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('custom_table_id');
            $table->dropIndex(['organisation_id', 'entity', 'custom_table_id']);
            $table->unique(['organisation_id', 'entity', 'key']);
        });

        Schema::dropIfExists('custom_records');
        Schema::dropIfExists('custom_tables');
    }
};
