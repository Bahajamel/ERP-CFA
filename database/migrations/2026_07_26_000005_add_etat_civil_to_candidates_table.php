<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État civil de l'apprenti complété depuis l'onglet « Étudiant » du dossier :
 * lieu de naissance et nationalité, attendus sur le CERFA. Le NIR / numéro de
 * sécurité sociale est VOLONTAIREMENT exclu (donnée sensible, cf. politique des
 * pièces sensibles).
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->string('nationalite')->nullable()->after('lieu_naissance');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn(['lieu_naissance', 'nationalite']);
        });
    }
};
