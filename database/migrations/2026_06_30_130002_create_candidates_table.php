<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('adresse')->nullable();
            $table->foreignId('formation_visee_id')->nullable()->constrained('formations')->nullOnDelete();
            $table->string('niveau_actuel')->nullable();
            $table->string('mobilite')->nullable();
            $table->string('disponibilite')->nullable();
            $table->string('source')->nullable();
            $table->foreignId('commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut')->default('incomplet');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
