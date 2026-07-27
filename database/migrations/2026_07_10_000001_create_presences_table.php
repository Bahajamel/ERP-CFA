<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Présences (émargement, EPIC-14) : une ligne par apprenti et par séance, avec
 * le statut de présence et un commentaire éventuel. Le justificatif d'absence
 * est rattaché en GED (Document polymorphe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('statut')->default('non_renseigne');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['seance_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
