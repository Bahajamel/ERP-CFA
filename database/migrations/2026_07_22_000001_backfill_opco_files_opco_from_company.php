<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renseigne l'OPCO des dossiers existants qui n'en ont pas, à partir de l'OPCO
 * de l'entreprise du contrat (déduit du SIRET dans Entreprises partenaires).
 *
 * Les dossiers étaient créés sans `opco_id` : l'information de l'entreprise
 * n'était pas reprise. On la persiste ici pour les dossiers déjà en base
 * (les nouveaux la reçoivent désormais à la création).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('opco_files')
            ->join('contracts', 'contracts.id', '=', 'opco_files.contract_id')
            ->join('companies', 'companies.id', '=', 'contracts.company_id')
            ->whereNull('opco_files.opco_id')
            ->whereNotNull('companies.opco_id')
            ->select('opco_files.id as of_id', 'companies.opco_id as company_opco_id')
            ->get()
            ->each(function ($row) {
                DB::table('opco_files')
                    ->where('id', $row->of_id)
                    ->update(['opco_id' => $row->company_opco_id]);
            });
    }

    public function down(): void
    {
        // Backfill de données : pas de restauration (l'OPCO reste consultable
        // via l'entreprise de toute façon).
    }
};
