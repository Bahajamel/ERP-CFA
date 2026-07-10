<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige les bases déjà migrées : convertit notifications.data de text vers json
 * (PostgreSQL refuse l'opérateur ->> sur du text, utilisé par Filament).
 * Spécifique PostgreSQL ; no-op ailleurs (SQLite/MySQL gèrent déjà).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications') && DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
