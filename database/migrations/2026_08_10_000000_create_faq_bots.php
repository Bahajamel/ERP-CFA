<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assistants FAQ par grande partie du logiciel (Commercial, Contrats & OPCO,
 * Finance, Scolarité, Pilotage & Administration). Chacun porte sa propre
 * identité : nom, couleur, icône, avatar, message d'accueil.
 *
 * Cloisonné par CFA : chaque organisation rédige ses propres assistants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_bots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            // Partie du logiciel couverte (commercial, contrats, finance…).
            $table->string('module');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();          // heroicon, repli si pas d'avatar
            $table->string('color')->default('#6366f1'); // teinte de l'assistant
            $table->string('avatar_path')->nullable();   // image téléversée (facultative)
            $table->text('welcome_message');
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();

            // Un seul assistant par partie et par CFA.
            $table->unique(['organisation_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_bots');
    }
};
