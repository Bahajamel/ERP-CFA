<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Échéancier des versements OPCO (décret n° 2025-585 du 27/06/2025).
 * Contrats ≥ 12 mois : 40 % (J+30), 30 % (7e mois), 20 % (10e mois), 10 % (solde).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opco_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opco_file_id')->constrained('opco_files')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre')->default(1);
            $table->string('libelle');
            $table->decimal('pourcentage', 5, 2);
            $table->decimal('montant_prevu', 10, 2)->default(0);
            $table->decimal('montant_verse', 10, 2)->nullable();
            $table->date('date_prevue');
            $table->date('date_versement')->nullable();
            $table->string('statut')->default('attendu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opco_payments');
    }
};
