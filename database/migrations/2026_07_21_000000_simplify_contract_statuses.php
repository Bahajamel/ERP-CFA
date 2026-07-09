<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Resserre les statuts de contrat de 9 à 4 (+ « Rompu » pour la rupture) :
 *   brouillon / infos_manquantes / pret_a_verifier   → en_cours
 *   envoye_signature                                  → manque_signature
 *   signe / transmis_opco / actif / archive           → complet
 *   rompu                                             → rompu (inchangé)
 *
 * Migration additive et idempotente : convertit les données existantes et
 * ajuste la valeur par défaut de la colonne.
 */
return new class extends Migration
{
    private const MAPPING = [
        'brouillon' => 'en_cours',
        'infos_manquantes' => 'en_cours',
        'pret_a_verifier' => 'en_cours',
        'envoye_signature' => 'manque_signature',
        'signe' => 'complet',
        'transmis_opco' => 'complet',
        'actif' => 'complet',
        'archive' => 'complet',
    ];

    public function up(): void
    {
        foreach (self::MAPPING as $ancien => $nouveau) {
            DB::table('contracts')->where('statut_contrat', $ancien)->update(['statut_contrat' => $nouveau]);
        }

        // Nouvelle valeur par défaut (contrat créé « En cours »). SQLite ne
        // sait pas modifier une valeur par défaut sans reconstruire la table ;
        // en test (sqlite) le défaut n'est pas requis, on ne l'applique qu'en base réelle.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts ALTER COLUMN statut_contrat SET DEFAULT 'en_cours'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts ALTER COLUMN statut_contrat SET DEFAULT 'brouillon'");
        }
    }
};
