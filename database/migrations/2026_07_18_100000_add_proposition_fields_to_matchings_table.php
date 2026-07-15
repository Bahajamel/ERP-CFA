<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulaire « Proposer des candidats » : traçabilité de la proposition envoyée
 * à l'entreprise (canal d'envoi, responsable du suivi, message et commentaire
 * interne, date de proposition). La date de relance réutilise `next_action_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchings', function (Blueprint $table) {
            $table->string('canal')->nullable()->after('cv_envoye');
            $table->foreignId('responsable_suivi_id')->nullable()->after('assigned_by')
                ->constrained('users')->nullOnDelete();
            $table->text('message_presentation')->nullable()->after('notes');
            $table->text('commentaire_interne')->nullable()->after('message_presentation');
            $table->date('date_proposition')->nullable()->after('commentaire_interne');
        });
    }

    public function down(): void
    {
        Schema::table('matchings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_suivi_id');
            $table->dropColumn(['canal', 'message_presentation', 'commentaire_interne', 'date_proposition']);
        });
    }
};
