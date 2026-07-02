<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité de l'origine d'un document. « source » distingue les pièces
 * déposées à la main de celles générées par LivretRS ; « livrable_code »
 * mémorise le code du livrable LivretRS d'origine (ex. « livret_accueil »)
 * pour la régénération et l'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('source')->default('manuel')->after('type'); // manuel | livretrs
            $table->string('livrable_code')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['source', 'livrable_code']);
        });
    }
};
