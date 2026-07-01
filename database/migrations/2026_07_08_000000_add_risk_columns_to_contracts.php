<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instantané du risque de rupture sur les contrats, recalculé quotidiennement.
 * Stocké (plutôt que calculé à la volée) pour trier/filtrer côté base et
 * alimenter le dashboard et les alertes sans surcoût par requête.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('risk_score')->nullable()->after('commentaire');
            $table->string('risk_level')->nullable()->index()->after('risk_score');
            // json (et non text) : requis par PostgreSQL pour d'éventuelles requêtes ->>.
            $table->json('risk_factors')->nullable()->after('risk_level');
            $table->timestamp('risk_evaluated_at')->nullable()->after('risk_factors');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['risk_score', 'risk_level', 'risk_factors', 'risk_evaluated_at']);
        });
    }
};
