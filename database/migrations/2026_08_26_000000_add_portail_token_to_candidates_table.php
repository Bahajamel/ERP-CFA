<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton d'accès personnel de l'apprenant à son espace (portail sans mot de passe,
 * dans la lignée des liens tokenisés existants — inscription, émargement). Émis à
 * la demande, révocable (régénération = ancien lien invalidé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->string('portail_token', 64)->nullable()->unique()->after('position');
            $table->timestamp('portail_token_created_at')->nullable()->after('portail_token');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn(['portail_token', 'portail_token_created_at']);
        });
    }
};
