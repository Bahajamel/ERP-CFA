<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séances de formation (EPIC-14) : une séance = un créneau daté rattaché à une
 * promotion, support de l'émargement (présences).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->date('date');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->string('libelle')->nullable();
            $table->foreignId('formateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut')->default('planifiee');
            $table->timestamps();

            $table->index(['promotion_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
