<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions / réponses d'un assistant FAQ. Le contenu est modifiable depuis
 * l'administration, sans toucher au code.
 *
 * `link_*` reprend le comportement de l'assistant actuel : une réponse peut
 * renvoyer vers la bonne page de l'ERP, le lien restant masqué si l'utilisateur
 * n'a pas la permission associée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('faq_bot_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            // Mots-clés de recherche (liste), en plus des mots du titre.
            $table->json('keywords')->nullable();
            $table->string('category')->nullable();
            $table->string('link_route')->nullable();
            $table->string('link_label')->nullable();
            $table->string('link_permission')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['faq_bot_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_entries');
    }
};
