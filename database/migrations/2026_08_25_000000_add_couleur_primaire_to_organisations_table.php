<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label par CFA : couleur principale de l'espace (boutons, liens, accents).
 * Chaque CFA personnalise sa teinte en plus de son logo et de son nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('couleur_primaire', 7)->nullable()->after('theme_defaut');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('couleur_primaire');
        });
    }
};
