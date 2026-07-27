<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de démonstration déposées depuis le site vitrine public.
 *
 * Volontairement HORS multi-tenant (pas de colonne organisation_id) : ce sont
 * des prospects, pas encore des CFA clients. Elles sont traitées par l'éditeur
 * de la plateforme, qui décide ensuite d'ouvrir — ou non — un CFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table): void {
            $table->id();

            // Contact
            $table->string('first_name');
            $table->string('last_name');
            $table->string('job_title')->nullable();
            $table->string('organization_name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Qualification
            $table->string('learner_count')->nullable();
            $table->string('main_need')->nullable();
            $table->text('message')->nullable();

            // Suivi commercial
            $table->string('status')->default('nouveau')->index();
            $table->text('notes_internes')->nullable();

            // Traçabilité du consentement (RGPD) et de l'origine de la demande
            $table->timestamp('consent_at')->nullable();
            $table->string('ip')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
