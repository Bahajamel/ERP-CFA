<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('need_id')->constrained('needs')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('statut')->default('propose');
            $table->boolean('cv_envoye')->default(false);
            $table->date('date_entretien')->nullable();
            $table->text('retour_entreprise')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['need_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchings');
    }
};
