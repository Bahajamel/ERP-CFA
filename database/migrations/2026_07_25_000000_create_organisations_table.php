<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'organisation = un CFA client (multi-tenant Filament). Le CFA « maison »
 * (V2S) est une organisation comme les autres. Chaque utilisateur (personnel du
 * CFA) est rattaché à une ou plusieurs organisations via la table pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();   // identifiant d'URL du tenant (ex. sous-domaine plus tard)
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('organisation_user', function (Blueprint $table) {
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['organisation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_user');
        Schema::dropIfExists('organisations');
    }
};
