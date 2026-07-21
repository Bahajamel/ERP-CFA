<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Informations administratives de l'entreprise (onglet « Entreprise » du
 * dossier), nécessaires au CERFA / à la convention / au dossier OPCO :
 * code APE-NAF, IDCC, convention collective, caisse de retraite complémentaire,
 * effectif, type d'employeur (et son type spécifique).
 *
 * Ces données appartiennent à l'entreprise (réutilisées d'un contrat à l'autre).
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('code_ape_naf')->nullable();
            $table->string('code_idcc')->nullable();
            $table->string('convention_collective')->nullable();
            $table->string('caisse_retraite')->nullable();
            $table->unsignedInteger('nombre_salaries')->nullable();
            $table->string('type_employeur')->nullable();
            $table->string('type_employeur_specifique')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'code_ape_naf', 'code_idcc', 'convention_collective', 'caisse_retraite',
                'nombre_salaries', 'type_employeur', 'type_employeur_specifique',
            ]);
        });
    }
};
