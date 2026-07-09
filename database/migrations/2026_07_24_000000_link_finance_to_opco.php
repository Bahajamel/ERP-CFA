<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connecte la finance au dossier OPCO :
 *  - `finance_lines.opco_file_id` : la ligne financière générée automatiquement
 *    à l'acceptation du dossier OPCO (montant accepté repris) ;
 *  - `invoices.opco_payment_id` : l'échéance de versement (décret 2025-585) que
 *    la facture couvre — évite de facturer deux fois la même échéance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_lines', function (Blueprint $table): void {
            $table->foreignId('opco_file_id')->nullable()->after('contract_id')
                ->constrained('opco_files')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('opco_payment_id')->nullable()->after('finance_line_id')
                ->constrained('opco_payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opco_payment_id');
        });

        Schema::table('finance_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opco_file_id');
        });
    }
};
