<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Généralisation « façon Monday » : une table personnalisée est rattachée à un
 * CONTEXTE de module (ex. « candidate », « company », « need »), afin qu'un CFA
 * puisse créer PLUSIEURS tableaux par module (plusieurs boards Candidats, plusieurs
 * boards Entreprises, etc.). null = table autonome (gérée dans Administration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->string('context')->nullable()->after('name');
            $table->index(['organisation_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->dropIndex(['organisation_id', 'context']);
            $table->dropColumn('context');
        });
    }
};
