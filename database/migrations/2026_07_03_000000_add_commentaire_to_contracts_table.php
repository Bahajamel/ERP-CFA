<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive : commentaire libre sur le contrat (justification de
 * signature, notes de transition…). Ne modifie aucune colonne existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->text('commentaire')->nullable()->after('statut_contrat');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('commentaire');
        });
    }
};
