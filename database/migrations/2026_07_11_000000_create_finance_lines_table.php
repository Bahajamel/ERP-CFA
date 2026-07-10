<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ligne financière rattachée à un contrat (P1-16-1) : le suivi des montants
 * attendu / accepté / bloqué. Facturé & encaissé sont dérivés des factures
 * et des paiements liés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('libelle');
            $table->decimal('montant_attendu', 12, 2)->default(0);
            $table->decimal('montant_accepte', 12, 2)->default(0);
            $table->decimal('montant_bloque', 12, 2)->default(0);
            $table->text('motif_blocage')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_lines');
    }
};
