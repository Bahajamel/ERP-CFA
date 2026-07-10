<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opco_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('opco_id')->nullable()->constrained('opcos')->nullOnDelete();
            $table->date('date_depot')->nullable();
            $table->string('statut')->default('non_cree');
            $table->decimal('montant_prevu', 10, 2)->nullable();
            $table->decimal('montant_accepte', 10, 2)->nullable();
            $table->text('motif_rejet')->nullable();
            $table->foreignId('responsable_correction_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_relance')->nullable();
            $table->text('commentaire_interne')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opco_files');
    }
};
