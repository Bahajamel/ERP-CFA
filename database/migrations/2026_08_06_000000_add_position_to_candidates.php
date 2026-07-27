<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ordre manuel des candidats sur la « Base Candidats » (glisser-déposer des
 * lignes « façon Monday », cohérent avec les tableaux personnalisés). Backfill
 * par CFA (organisation), dans l'ordre de création (id croissant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->integer('position')->default(0)->after('id');
            $table->index(['organisation_id', 'position']);
        });

        // Backfill : position 0..n par CFA, dans l'ordre de création.
        DB::table('candidates')
            ->select('organisation_id')
            ->distinct()
            ->pluck('organisation_id')
            ->each(function ($orgId): void {
                $position = 0;

                DB::table('candidates')
                    ->where('organisation_id', $orgId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function ($id) use (&$position): void {
                        DB::table('candidates')->where('id', $id)->update(['position' => $position]);
                        $position++;
                    });
            });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropIndex(['organisation_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
