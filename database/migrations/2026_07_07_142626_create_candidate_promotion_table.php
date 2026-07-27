<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un apprenant suit PLUSIEURS classes (une classe = une matière) au sein de
 * SA formation : la FK candidates.promotion_id devient une table pivot.
 * Les rattachements existants sont repris avant suppression de la colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['candidate_id', 'promotion_id']);
        });

        // Reprise : chaque rattachement simple devient une ligne du pivot.
        DB::table('candidates')->whereNotNull('promotion_id')->orderBy('id')
            ->each(fn ($c) => DB::table('candidate_promotion')->insert([
                'candidate_id' => $c->id,
                'promotion_id' => $c->promotion_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('formation_visee_id')
                ->constrained('promotions')->nullOnDelete();
        });

        // Restaure au mieux : la première classe de chaque apprenant.
        DB::table('candidate_promotion')->orderBy('id')->each(function ($ligne): void {
            DB::table('candidates')->where('id', $ligne->candidate_id)->whereNull('promotion_id')
                ->update(['promotion_id' => $ligne->promotion_id]);
        });

        Schema::dropIfExists('candidate_promotion');
    }
};
