<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité de la suppression (corbeille candidats) : motif obligatoire saisi
 * à la suppression + auteur. Le `deleted_at` (SoftDeletes) existe déjà. Le
 * candidat reste 30 jours en corbeille (restaurable) avant purge automatique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->text('motif_suppression')->nullable()->after('statut');
            $table->foreignId('deleted_by')->nullable()->after('motif_suppression')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('motif_suppression');
        });
    }
};
