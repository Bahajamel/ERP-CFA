<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permet d'activer/désactiver le formulaire public d'un tableau indépendamment de
 * son archivage : on peut fermer le lien de candidature/entreprise sans supprimer
 * le tableau. Activé par défaut (comportement actuel inchangé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->boolean('public_enabled')->default(true)->after('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->dropColumn('public_enabled');
        });
    }
};
