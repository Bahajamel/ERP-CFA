<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale');
            $table->string('nom_commercial')->nullable();
            $table->string('siret')->unique();
            $table->string('adresse')->nullable();
            $table->string('secteur')->nullable();
            $table->foreignId('opco_id')->nullable()->constrained('opcos')->nullOnDelete();
            $table->string('statut')->default('prospect');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('fonction')->nullable();
            $table->boolean('is_principal')->default(false);
            $table->boolean('is_tuteur')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('companies');
    }
};
