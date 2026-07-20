<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lot 1 (Fondations) — Enrichit les tables personnalisées pour une solution
 * réellement livrable à plusieurs CFA : identifiant lisible (slug), description,
 * couleur, activation/archivage, auteur, et archivage réversible (soft delete).
 *
 * Additif uniquement : aucune colonne existante n'est touchée. L'unicité du slug
 * (par CFA) est garantie en code — comme pour les clés de colonnes — pour rester
 * cohérent et éviter les blocages sur des données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->string('color')->nullable()->after('icon');
            $table->boolean('is_active')->default(true)->after('color');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index(['organisation_id', 'slug']);
        });

        // Backfill : un slug lisible et unique par CFA pour les tables existantes.
        $vus = [];
        foreach (DB::table('custom_tables')->get() as $t) {
            $base = Str::slug($t->name) ?: 'table';
            $slug = $base;
            $i = 2;
            while (in_array($t->organisation_id.'|'.$slug, $vus, true)) {
                $slug = $base.'-'.$i;
                $i++;
            }
            $vus[] = $t->organisation_id.'|'.$slug;
            DB::table('custom_tables')->where('id', $t->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('custom_tables', function (Blueprint $table): void {
            $table->dropIndex(['organisation_id', 'slug']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropSoftDeletes();
            $table->dropColumn(['slug', 'description', 'color', 'is_active']);
        });
    }
};
