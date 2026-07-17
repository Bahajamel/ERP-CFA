<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache les entités centrales (candidats, entreprises, besoins) à un CFA
 * (organisation). Colonne nullable + backfill vers l'organisation par défaut :
 * migration non destructive, l'existant est rattaché au CFA « maison ».
 */
return new class extends Migration
{
    /** Tables métier centrales rattachées à une organisation dans ce lot. */
    private array $tables = ['candidates', 'companies', 'needs'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('organisation_id')->nullable()->after('id')
                    ->constrained()->nullOnDelete();
            });
        }

        // Backfill : tout l'existant appartient au CFA par défaut (le premier).
        $defaultId = DB::table('organisations')->min('id');

        if ($defaultId !== null) {
            foreach ($this->tables as $table) {
                DB::table($table)->whereNull('organisation_id')->update(['organisation_id' => $defaultId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('organisation_id');
            });
        }
    }
};
