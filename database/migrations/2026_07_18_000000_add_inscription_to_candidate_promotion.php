<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription à la carte : quand une admission est validée, l'apprenant est
 * rattaché à une cohorte et invité (lien tokenisé) à choisir ses matières au
 * sein du programme de la formation. Une ligne du pivot capture à la fois
 * l'appartenance à la classe, l'invitation et la réponse de l'apprenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_promotion', function (Blueprint $table) {
            // Matières choisies par l'apprenant au sein du programme (null tant
            // qu'il n'a pas répondu). Ne restreint pas la scolarité (traçabilité
            // du choix) — cf. décision « enregistrer le choix sans refiltrer ».
            $table->json('matieres')->nullable()->after('promotion_id');
            $table->string('invitation_token', 64)->nullable()->unique()->after('matieres');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
            $table->timestamp('responded_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_promotion', function (Blueprint $table) {
            $table->dropColumn(['matieres', 'invitation_token', 'invited_at', 'responded_at']);
        });
    }
};
