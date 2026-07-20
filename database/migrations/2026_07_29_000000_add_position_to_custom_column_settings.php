<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordre des colonnes « façon Monday » : position mémorisée par CFA, alimentée par
 * le glisser-déposer des en-têtes. null = colonne non repositionnée (elle garde
 * son ordre d'origine, après les colonnes ordonnées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_column_settings', function (Blueprint $table): void {
            $table->integer('position')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('custom_column_settings', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
