<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encaissements (P1-16-2). Un paiement est toujours relié à une ligne financière
 * et, optionnellement, à une facture précise (P1-16-4). Quand une facture est
 * intégralement encaissée, elle passe automatiquement à « Payée ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('montant', 12, 2);
            $table->date('date_paiement');
            $table->string('moyen')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('finance_line_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payments');
    }
};
