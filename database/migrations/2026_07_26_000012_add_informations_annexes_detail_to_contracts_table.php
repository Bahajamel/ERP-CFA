<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Précision saisie quand « Informations annexes obligatoires » vaut « N° de bon
 * de commande à mentionner sur la facture » ou « Autre(s) » : la valeur libre à
 * reporter sur la facture. Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('informations_annexes_detail')->nullable()->after('informations_annexes');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('informations_annexes_detail');
        });
    }
};
