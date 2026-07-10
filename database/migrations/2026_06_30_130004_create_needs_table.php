<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('intitule_poste');
            $table->foreignId('formation_id')->nullable()->constrained('formations')->nullOnDelete();
            $table->string('localisation')->nullable();
            $table->date('date_demarrage')->nullable();
            $table->unsignedSmallInteger('nb_postes')->default(1);
            $table->string('rythme')->nullable();
            $table->text('prerequis')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->foreignId('tuteur_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            $table->string('statut')->default('cree');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('needs');
    }
};
