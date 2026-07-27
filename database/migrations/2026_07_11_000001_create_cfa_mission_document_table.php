<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot Document ↔ Mission CFA. Un livrable peut prouver plusieurs
 * missions (ex. le livret d'accueil couvre les droits/devoirs ET la
 * sécurité) ; une mission est couverte par plusieurs documents. La
 * couverture des 14 missions se calcule à partir de ces liens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfa_mission_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cfa_mission_id')->constrained('cfa_missions')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cfa_mission_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfa_mission_document');
    }
};
