<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0-03-5 : typage des notes (note / compte rendu / incident / satisfaction) et
 * niveau de satisfaction optionnel (1 à 5) pour le suivi relation entreprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('type')->default('note')->after('notable_type');
            $table->unsignedTinyInteger('satisfaction')->nullable()->after('contenu');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['type', 'satisfaction']);
        });
    }
};
