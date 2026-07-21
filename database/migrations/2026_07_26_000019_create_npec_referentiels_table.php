<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des Niveaux de Prise en Charge (NPEC) des contrats
 * d'apprentissage, publié par France Compétences. Le NPEC est défini par
 * CERTIFICATION (code RNCP) et peut être modulé par branche (code IDCC).
 *
 * Table de référence NATIONALE (données publiques partagées) : PAS de
 * `organisation_id` ni de scope multi-tenant — le barème est le même pour
 * tous les CFA. Alimentée par import du fichier officiel + seed d'échantillon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npec_referentiels', function (Blueprint $table): void {
            $table->id();
            $table->string('code_rncp')->index();
            // IDCC de la branche ; null = valeur nationale par défaut pour ce RNCP.
            $table->string('code_idcc')->nullable()->index();
            $table->decimal('npec_annuel', 10, 2);
            $table->string('libelle')->nullable();
            $table->string('source')->nullable();
            $table->date('date_valeur')->nullable();
            $table->timestamps();

            // Un seul NPEC par couple (certification, branche).
            $table->unique(['code_rncp', 'code_idcc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npec_referentiels');
    }
};
