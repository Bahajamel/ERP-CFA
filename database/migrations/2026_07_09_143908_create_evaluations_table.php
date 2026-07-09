<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Évaluations (notes) des apprenants : une note par apprenant, matière et
 * épreuve, au sein d'une classe (cohorte). Base des bulletins (moyennes par
 * matière + générale, pondérées par coefficient).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('matiere');
            $table->string('type')->default('devoir');
            $table->decimal('note', 5, 2);            // sur 20 (jusqu'à 999.99 par sécurité)
            $table->decimal('bareme', 5, 2)->default(20); // note maximale de l'épreuve
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->date('date')->nullable();
            $table->text('appreciation')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['promotion_id', 'matiere']);
            $table->index(['candidate_id', 'matiere']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
