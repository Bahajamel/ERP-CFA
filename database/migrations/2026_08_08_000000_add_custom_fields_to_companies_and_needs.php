<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes personnalisées (couche « façon Monday ») pour les Entreprises et les
 * Offres, à l'image des Candidats : les valeurs vivent dans un JSONB `custom_fields`
 * adressé par clé. Les définitions restent dans custom_field_definitions (entity
 * = company | need), cloisonnées par CFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['companies', 'needs'] as $table) {
            if (! Schema::hasColumn($table, 'custom_fields')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->jsonb('custom_fields')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['companies', 'needs'] as $table) {
            if (Schema::hasColumn($table, 'custom_fields')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropColumn('custom_fields');
                });
            }
        }
    }
};
