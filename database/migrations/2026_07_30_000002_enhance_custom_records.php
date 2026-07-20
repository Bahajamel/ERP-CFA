<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1 (Fondations) — Lignes des tables personnalisées : traçabilité (auteur de
 * création/dernière modification) et archivage réversible (soft delete). Additif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_records', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('data')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('custom_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropSoftDeletes();
        });
    }
};
