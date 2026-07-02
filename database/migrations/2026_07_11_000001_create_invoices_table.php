<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factures rattachées à une ligne financière (P1-16-3). Une facture est d'abord
 * un brouillon (générée ou importée) puis émise (numérotée, montant + destinataire
 * obligatoires — P1-16-4), enfin payée ou annulée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_line_id')->constrained()->cascadeOnDelete();
            $table->string('numero')->nullable()->unique();
            $table->string('statut')->default('brouillon');
            $table->string('destinataire')->nullable();
            $table->decimal('montant', 12, 2)->default(0);
            $table->date('date_emission')->nullable();
            $table->date('date_echeance')->nullable();
            $table->text('commentaire')->nullable();
            $table->boolean('importee')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['finance_line_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
