<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index GIN sur le JSONB `custom_records.data` : accélère les filtres, tris et
 * recherches sur les valeurs des colonnes personnalisées à grande échelle.
 *
 * Spécifique à PostgreSQL (ignoré sur SQLite, utilisé en tests) : GIN n'existe
 * pas sur SQLite et n'y est pas nécessaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS custom_records_data_gin ON custom_records USING gin (data)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS custom_records_data_gin');
        }
    }
};
