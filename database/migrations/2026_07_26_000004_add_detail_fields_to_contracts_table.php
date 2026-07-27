<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs complétés depuis la fiche « tour de contrôle » du dossier (phase 2),
 * nécessaires au CERFA / à la convention et au suivi administratif :
 *  - date de signature du contrat (« fait à … le … ») ;
 *  - durée hebdomadaire de travail (le CERFA la posait en dur à 35 h) ;
 *  - emploi occupé par l'apprenti ;
 *  - responsable interne du dossier (utilisateur du CFA) ;
 *  - notes internes (suivi administratif, non imprimées sur les documents).
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->date('date_signature')->nullable()->after('date_fin');
            $table->unsignedSmallInteger('duree_hebdo_heures')->nullable()->after('date_signature');
            $table->string('emploi_occupe')->nullable()->after('duree_hebdo_heures');
            $table->foreignId('responsable_id')->nullable()->after('emploi_occupe')
                ->constrained('users')->nullOnDelete();
            $table->text('notes_internes')->nullable()->after('responsable_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsable_id');
            $table->dropColumn(['date_signature', 'duree_hebdo_heures', 'emploi_occupe', 'notes_internes']);
        });
    }
};
