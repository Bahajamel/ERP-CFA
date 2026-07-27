<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résultat de la dernière vérification RNCP en ligne (France Compétences) d'une
 * formation. Distinct de « is_active » (formation proposée au catalogue) :
 * ici on mémorise si la CERTIFICATION est encore valide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->boolean('rncp_actif')->nullable()->after('code_rncp');
            $table->string('rncp_etat')->nullable()->after('rncp_actif');
            $table->string('rncp_intitule')->nullable()->after('rncp_etat');
            $table->string('rncp_niveau')->nullable()->after('rncp_intitule');
            $table->timestamp('rncp_verifie_at')->nullable()->after('rncp_niveau');
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn(['rncp_actif', 'rncp_etat', 'rncp_intitule', 'rncp_niveau', 'rncp_verifie_at']);
        });
    }
};
