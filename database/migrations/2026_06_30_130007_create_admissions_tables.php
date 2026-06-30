<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained('candidates')->cascadeOnDelete();
            $table->string('statut')->default('a_verifier');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->string('document_type');
            $table->boolean('est_obligatoire')->default(true);
            $table->string('statut')->default('manquante');
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_checklist_items');
        Schema::dropIfExists('admissions');
    }
};
