<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service fait (EPIC-15) : attestation mensuelle figée de l'assiduité d'une
 * promotion (base de la facturation OPCO et des preuves Qualiopi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_faits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('nb_seances')->default(0);
            $table->decimal('nb_heures', 6, 1)->default(0);
            $table->unsignedTinyInteger('taux_presence')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['promotion_id', 'annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_faits');
    }
};
