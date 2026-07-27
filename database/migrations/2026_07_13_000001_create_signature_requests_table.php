<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande de signature électronique multi-parties d'un contrat (EPIC-08) :
 * employeur, apprenti, représentant légal (si mineur) et CFA. Trace l'enveloppe
 * chez le prestataire eIDAS et l'état de signature de chaque partie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_id')->nullable();       // id d'enveloppe chez le prestataire
            $table->string('statut')->default('brouillon')->index();
            $table->json('signataires');                     // [{role, nom, email, ordre, signe_at}]
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
