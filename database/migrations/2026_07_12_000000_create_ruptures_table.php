<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier de rupture d'un contrat d'apprentissage (EPIC-18) : motif, date,
 * accompagnement et recherche d'un nouvel employeur. Un contrat a au plus une
 * rupture (contract_id unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruptures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_rupture');
            $table->string('motif');
            $table->string('initiative')->nullable();
            $table->string('statut')->default('ouverte');
            $table->text('accompagnement')->nullable();
            $table->string('nouvel_employeur')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruptures');
    }
};
