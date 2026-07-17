<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache le reste des données par CFA (formation & scolarité, finance, suivi)
 * à une organisation. Nullable + backfill : non destructif.
 *
 * NB : les tables de RÉFÉRENCE nationale partagée (14 missions CFA, 32 indicateurs
 * Qualiopi, liste OPCO) restent volontairement SANS organisation_id — la
 * couverture/preuve propre à chaque CFA passe déjà par ses documents.
 */
return new class extends Migration
{
    private array $tables = [
        'formations', 'promotions', 'seances', 'notes', 'entretiens',
        'ruptures', 'evaluations', 'invoices', 'finance_lines', 'interactions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('organisation_id')->nullable()->after('id')
                    ->constrained()->nullOnDelete();
            });
        }

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
