<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentement RGPD de l'apprenant à la transmission de son CV aux entreprises
 * partenaires (base légale du partage d'une donnée personnelle à un tiers).
 * Sans ce consentement, le candidat ne peut pas être proposé sur un besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->boolean('cv_consentement')->default(false)->after('mobilite');
            $table->timestamp('cv_consentement_at')->nullable()->after('cv_consentement');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['cv_consentement', 'cv_consentement_at']);
        });
    }
};
