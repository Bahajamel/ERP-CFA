<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colonne d'ordre manuel des lignes d'un tableau personnalisé (glisser-déposer
 * « façon Monday »). Backfill : chaque tableau reçoit un ordre 0..n suivant
 * l'ordre de création (id croissant), pour préserver l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_records', function (Blueprint $table): void {
            $table->integer('position')->default(0)->after('data');
            $table->index(['custom_table_id', 'position']);
        });

        // Backfill : position 0..n par tableau, dans l'ordre de création.
        DB::table('custom_records')
            ->select('custom_table_id')
            ->distinct()
            ->pluck('custom_table_id')
            ->each(function ($tableId): void {
                $position = 0;

                DB::table('custom_records')
                    ->where('custom_table_id', $tableId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function ($id) use (&$position): void {
                        DB::table('custom_records')->where('id', $id)->update(['position' => $position]);
                        $position++;
                    });
            });
    }

    public function down(): void
    {
        Schema::table('custom_records', function (Blueprint $table): void {
            $table->dropIndex(['custom_table_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
