<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton d'accès personnel de l'entreprise à son espace (portail sans mot de passe,
 * pendant [[portail-apprenant]] côté employeur). Émis à la demande, révocable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('portail_token', 64)->nullable()->unique();
            $table->timestamp('portail_token_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['portail_token', 'portail_token_created_at']);
        });
    }
};