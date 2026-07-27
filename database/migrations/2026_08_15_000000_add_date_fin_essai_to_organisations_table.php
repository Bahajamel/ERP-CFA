<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Essai gratuit d'un CFA : date d'échéance après laquelle son accès est
 * suspendu (bascule de `actif` à false par la commande essai:suspendre-expires).
 *
 * Nulle = CFA client sans notion d'essai (l'ancien comportement, inchangé).
 * Le blocage d'accès reste porté par la colonne `actif` existante ; cette date
 * ne fait que déclencher la bascule à l'échéance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->timestamp('date_fin_essai')->nullable()->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('date_fin_essai');
        });
    }
};
