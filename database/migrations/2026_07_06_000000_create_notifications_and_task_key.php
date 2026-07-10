<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications in-app (cloche Filament) + clé d'alerte sur les tâches pour
 * éviter les doublons d'alertes automatiques (une alerte = une clé unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                // json (et non text) : Filament interroge data->>'format' — requis par PostgreSQL.
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('cle')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('cle');
        });

        Schema::dropIfExists('notifications');
    }
};
