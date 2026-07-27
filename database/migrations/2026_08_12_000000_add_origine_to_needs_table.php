<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche besoin publique : une entreprise peut désormais décrire elle-même son
 * besoin (2ᵉ étape du formulaire /entreprise). Ces offres n'entrent pas
 * directement dans le circuit de recrutement — un commercial les relit d'abord.
 *
 * Le défaut « interne » garantit qu'AUCUNE offre existante ne bascule en
 * attente de validation : seules celles déposées par une entreprise le sont.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table): void {
            $table->string('origine')->default('interne')->after('statut');
            $table->timestamp('validee_at')->nullable()->after('origine');
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table): void {
            $table->dropColumn(['origine', 'validee_at']);
        });
    }
};
