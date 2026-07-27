<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache le pipeline opérationnel (matchings, contrats, admissions, dossiers
 * OPCO, documents, tâches) à un CFA. Nullable + backfill : non destructif.
 */
return new class extends Migration
{
    private array $tables = ['matchings', 'contracts', 'admissions', 'opco_files', 'documents', 'tasks'];

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
