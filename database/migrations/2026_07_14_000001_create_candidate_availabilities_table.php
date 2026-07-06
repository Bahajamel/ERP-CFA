<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disponibilités du candidat (structure évolutive) : plusieurs périodes de
 * disponibilité ou d'indisponibilité, avec dates, option « immédiate » et
 * commentaire. Remplace à terme le champ texte libre `candidates.disponibilite`
 * (conservé pour ne rien perdre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('disponible'); // disponible | indisponible
            $table->boolean('immediate')->default(false);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_availabilities');
    }
};
