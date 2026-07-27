<?php

use App\Models\OpcoFile;
use App\Services\FinanceService;
use Illuminate\Database\Migrations\Migration;

/**
 * Génère la ligne financière des dossiers OPCO déjà acceptés qui n'en ont pas
 * encore (la génération automatique ne vaut que pour les futures acceptations).
 * Ne touche pas aux contrats disposant déjà d'une ligne financière (manuelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(FinanceService::class);

        OpcoFile::query()
            ->whereIn('statut', ['accepte', 'cloture'])
            ->where('montant_accepte', '>', 0)
            ->whereDoesntHave('financeLine')
            ->with('contract')
            ->get()
            ->each(function (OpcoFile $dossier) use ($service): void {
                // Ne pas doubler une ligne déjà saisie manuellement sur le contrat.
                if ($dossier->contract?->financeLines()->exists()) {
                    return;
                }

                $service->synchroniserDepuisOpco($dossier);
            });
    }

    public function down(): void
    {
        // Backfill de données : pas de restauration.
    }
};
