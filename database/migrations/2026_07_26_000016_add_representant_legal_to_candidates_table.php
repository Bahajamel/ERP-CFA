<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Représentant légal de l'apprenti : le CERFA le réclame lorsque l'apprenti est
 * mineur non émancipé (nom/prénom, adresse, courriel). Ces informations
 * n'existaient sur aucun modèle ; on les stocke sur l'apprenti lui-même.
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->string('repr_legal_nom')->nullable();
            $table->string('repr_legal_prenom')->nullable();
            $table->string('repr_legal_adresse')->nullable();
            $table->string('repr_legal_complement')->nullable();
            $table->string('repr_legal_code_postal')->nullable();
            $table->string('repr_legal_ville')->nullable();
            $table->string('repr_legal_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn([
                'repr_legal_nom', 'repr_legal_prenom', 'repr_legal_adresse',
                'repr_legal_complement', 'repr_legal_code_postal', 'repr_legal_ville', 'repr_legal_email',
            ]);
        });
    }
};
