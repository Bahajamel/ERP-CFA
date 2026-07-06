<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier de rupture d'un contrat d'apprentissage (EPIC-18) : ouverture,
 * accompagnement de l'apprenti, recherche d'un nouvel employeur (reclassement)
 * et clôture. Un dossier par contrat (relation 1—1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rupture_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_rupture');
            $table->string('motif');
            $table->string('initiateur');
            $table->string('statut')->default('ouvert')->index();
            $table->text('motif_detail')->nullable();
            $table->text('accompagnement')->nullable();
            $table->boolean('recherche_employeur')->default(false);
            $table->foreignId('nouvelle_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_cloture')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rupture_cases');
    }
};
