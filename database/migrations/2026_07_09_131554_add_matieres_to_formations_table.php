<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue de la formation : la liste de ses matières (son programme).
 * Stockée en JSON — simple liste de libellés, éditable sur la fiche formation
 * et proposée ensuite comme matières de séances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->json('matieres')->nullable()->after('rythme_defaut');
        });
    }

    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn('matieres');
        });
    }
};
