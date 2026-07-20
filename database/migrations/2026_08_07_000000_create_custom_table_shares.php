<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partages d'un tableau personnalisé : le créateur invite d'autres utilisateurs
 * du CFA à VOIR (lecture) ou MODIFIER (modification) le tableau. Sans partage, un
 * tableau reste privé à son créateur (et aux profils Administrateur / Direction).
 * Cloisonné par CFA. Un seul partage par (tableau, utilisateur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_table_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('custom_table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('lecture'); // lecture | modification
            $table->timestamps();

            $table->unique(['custom_table_id', 'user_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_table_shares');
    }
};
