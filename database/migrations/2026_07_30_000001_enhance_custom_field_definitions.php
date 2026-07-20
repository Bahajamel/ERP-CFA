<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1 (Fondations) — Colonnes personnalisées : rend une colonne obligatoire ou
 * non, permet une valeur par défaut et des règles de validation par type. Additif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table): void {
            $table->boolean('is_required')->default(false)->after('visible_table');
            $table->jsonb('default_value')->nullable()->after('is_required');
            $table->jsonb('validation_rules')->nullable()->after('default_value');
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table): void {
            $table->dropColumn(['is_required', 'default_value', 'validation_rules']);
        });
    }
};
