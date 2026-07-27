<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles d'e-mail (« mails types ») réutilisables par le CFA : objet + corps
 * avec variables ({{entreprise}}, {{offre}}…), envoyés depuis une offre au contact
 * de l'entreprise. Cloisonné par CFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
