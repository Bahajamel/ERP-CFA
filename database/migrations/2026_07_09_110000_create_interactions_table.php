<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interactions commerciales polymorphes (P0-03-6) : historique des échanges
 * (appels, e-mails, RDV, visites) rattachables à une entreprise (ou autre),
 * avec une prochaine action datée servant de relance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('interactable');
            $table->string('type')->default('autre');
            $table->date('date_interaction');
            $table->text('resume');
            $table->string('prochaine_action')->nullable();
            $table->date('prochaine_action_le')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('prochaine_action_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
