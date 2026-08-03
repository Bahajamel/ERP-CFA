<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton de signature au niveau de la SÉANCE (émargement dématérialisé, V2) : un
 * seul QR pour toute la classe. L'apprenant scanne ce QR, choisit son nom dans la
 * liste, puis signe — plus besoin d'un QR par étudiant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table): void {
            $table->string('signature_token', 64)->nullable()->unique()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table): void {
            $table->dropColumn('signature_token');
        });
    }
};
