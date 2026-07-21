<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onglet « Entreprise » du dossier — champs propres au contrat :
 *  - lieu de réalisation (exécution) du contrat, structuré ;
 *  - second maître d'apprentissage (présence + lien contact) ;
 *  - informations administratives (n° d'accord préalable, régime assurance
 *    chômage, financement CNFPT) ;
 *  - règlement du reste à charge (entité à facturer, adresse, contact, modalité
 *    d'envoi, destinataires e-mail multiples) ;
 *  - informations annexes obligatoires.
 *
 * Ces informations sont propres au dossier (elles peuvent différer de la fiche
 * entreprise partagée). Migration additive et douce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            // Lieu de réalisation (exécution) du contrat
            $table->string('lieu_execution')->nullable();
            $table->string('lieu_execution_numero')->nullable();
            $table->string('lieu_execution_complement')->nullable();
            $table->string('lieu_execution_code_postal')->nullable();
            $table->string('lieu_execution_ville')->nullable();
            $table->string('lieu_execution_pays')->nullable()->default('France');

            // Second maître d'apprentissage
            $table->boolean('second_maitre')->nullable();
            $table->foreignId('tuteur2_id')->nullable()->constrained('company_contacts')->nullOnDelete();

            // Informations administratives
            $table->string('numero_accord_prealable')->nullable();
            $table->boolean('regime_assurance_chomage')->nullable();
            $table->boolean('financement_cnfpt')->nullable();

            // Règlement du reste à charge
            $table->string('facturation_entite_nom')->nullable();
            $table->string('facturation_adresse')->nullable();
            $table->string('facturation_code_postal')->nullable();
            $table->string('facturation_ville')->nullable();
            $table->string('adresse_facturation_type')->nullable();
            $table->string('facturation_nom_societe')->nullable();
            $table->string('modalite_envoi_facture')->nullable();
            $table->json('facturation_emails')->nullable();

            // Informations annexes obligatoires
            $table->string('informations_annexes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tuteur2_id');
            $table->dropColumn([
                'lieu_execution', 'lieu_execution_numero', 'lieu_execution_complement',
                'lieu_execution_code_postal', 'lieu_execution_ville', 'lieu_execution_pays',
                'second_maitre', 'numero_accord_prealable', 'regime_assurance_chomage', 'financement_cnfpt',
                'facturation_entite_nom', 'facturation_adresse', 'facturation_code_postal', 'facturation_ville',
                'adresse_facturation_type', 'facturation_nom_societe', 'modalite_envoi_facture',
                'facturation_emails', 'informations_annexes',
            ]);
        });
    }
};
