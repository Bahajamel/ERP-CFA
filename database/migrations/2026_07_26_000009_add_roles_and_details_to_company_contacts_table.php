<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit les contacts de l'entreprise pour l'onglet « Entreprise » du dossier :
 *  - état civil / diplôme du maître d'apprentissage (attendus au CERFA) ;
 *  - deux nouveaux rôles : responsable administratif et financier, et contact de
 *    facturation (distincts du contact opérationnel, du représentant légal et
 *    du tuteur).
 *
 * Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_contacts', function (Blueprint $table): void {
            $table->date('date_naissance')->nullable();
            $table->string('niveau_diplome')->nullable();
            $table->string('diplome')->nullable();
            $table->boolean('is_responsable_financier')->default(false);
            $table->boolean('is_contact_facturation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('company_contacts', function (Blueprint $table): void {
            $table->dropColumn(['date_naissance', 'niveau_diplome', 'diplome', 'is_responsable_financier', 'is_contact_facturation']);
        });
    }
};
