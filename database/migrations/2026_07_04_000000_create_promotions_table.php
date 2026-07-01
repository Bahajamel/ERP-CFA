<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classes / promotions : un groupe d'apprentis rattaché à une formation et à
 * une année scolaire. Ajoute aussi le rattachement de l'apprenti à sa classe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->nullable()->constrained('formations')->nullOnDelete();
            $table->string('libelle');
            $table->string('annee_scolaire')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->timestamps();
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('formation_visee_id')
                ->constrained('promotions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });

        Schema::dropIfExists('promotions');
    }
};
