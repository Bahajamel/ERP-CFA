<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nettoyage de données : la règle a changé — une admission officielle ne
 * s'ouvre plus qu'à l'ACCEPTATION du dossier OPCO (et non à son simple dépôt).
 *
 * On retire donc les admissions « prématurées » créées sous l'ancienne règle :
 * celles encore « À vérifier » dont le dossier OPCO n'est pas accepté/clôturé
 * (ex. un dossier rejeté qui n'aurait jamais dû arriver en Admissions).
 *
 * Prudence : on ne touche PAS aux admissions déjà « Validé » ou en « Rupture »
 * (historique métier). Suppression ciblée et sûre.
 */
return new class extends Migration
{
    public function up(): void
    {
        $aSupprimer = DB::table('admissions')
            ->where('admissions.statut', 'a_verifier')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('opco_files')
                    ->whereColumn('opco_files.contract_id', 'admissions.contract_id')
                    ->whereIn('opco_files.statut', ['accepte', 'cloture']);
            })
            ->pluck('admissions.id');

        if ($aSupprimer->isEmpty()) {
            return;
        }

        // Les items de checklist éventuels partent avec (intégrité).
        DB::table('admission_checklist_items')->whereIn('admission_id', $aSupprimer)->delete();
        DB::table('admissions')->whereIn('id', $aSupprimer)->delete();
    }

    public function down(): void
    {
        // Nettoyage de données : pas de restauration (les admissions concernées
        // se recréeront automatiquement dès que leur dossier OPCO sera accepté).
    }
};
