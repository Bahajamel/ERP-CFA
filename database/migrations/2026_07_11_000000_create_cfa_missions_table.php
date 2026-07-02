<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des 14 missions du CFA (article L6231-2 du Code du travail,
 * rédaction issue de la loi n° 2018-771 du 5 septembre 2018, en vigueur au
 * 1er janvier 2019). Table de référence seedée — pilote la couverture
 * documentaire des livrables (module LivretRS) et la preuve d'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfa_missions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('numero')->unique(); // 1° à 14°
            $table->string('code')->unique();                // code stable (ex. « orientation »)
            $table->string('titre');                         // libellé court
            $table->text('texte');                           // texte officiel de l'alinéa
            $table->string('reference')->default('L6231-2'); // article de référence
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfa_missions');
    }
};
