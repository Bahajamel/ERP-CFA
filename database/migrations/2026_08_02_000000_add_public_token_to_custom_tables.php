<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Chaque tableau personnalisé peut avoir un LIEN DE CANDIDATURE public : un jeton
 * imprévisible ouvre un formulaire (sans accès à l'ERP) qui crée une LIGNE dans CE
 * tableau. Le jeton est unique et généré à la création (backfill des existants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->string('public_token', 64)->nullable()->unique()->after('slug');
        });

        foreach (DB::table('custom_tables')->whereNull('public_token')->pluck('id') as $id) {
            DB::table('custom_tables')->where('id', $id)->update(['public_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->dropColumn('public_token');
        });
    }
};
