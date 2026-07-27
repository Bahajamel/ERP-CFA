<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 6 — Vues enregistrées d'un tableau personnalisé : un CFA sauvegarde un
 * agencement (colonnes visibles, ordre, tri, filtres) sous un nom, et peut en
 * définir une par défaut. Cloisonné par CFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('custom_table_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('filters')->nullable();
            $table->jsonb('sort')->nullable();
            $table->jsonb('visible_columns')->nullable();
            $table->jsonb('column_order')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['custom_table_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_views');
    }
};
