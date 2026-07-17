<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend les unicités métier relatives au CFA. Deux contraintes posées à l'époque
 * mono-CFA sont globales et cassent le multi-tenant :
 *
 * - `companies.siret` : deux CFA ne peuvent pas avoir la même entreprise
 *   partenaire, alors qu'un même employeur travaille couramment avec plusieurs
 *   centres. Chaque CFA gère sa propre fiche entreprise.
 * - `invoices.numero` : la numérotation des factures est propre à chaque CFA
 *   (obligation comptable de séquence continue par émetteur) ; deux CFA
 *   émettent légitimement une facture « 2026-001 ».
 *
 * L'unicité reste garantie *à l'intérieur* d'un CFA via un index composite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique('companies_siret_unique');
            $table->unique(['organisation_id', 'siret']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_numero_unique');
            $table->unique(['organisation_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'siret']);
            $table->unique('siret');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'numero']);
            $table->unique('numero');
        });
    }
};
