<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use Illuminate\Console\Command;

/**
 * Purge définitive des candidats en corbeille depuis plus de
 * {@see Candidate::DELAI_PURGE_JOURS} jours. Le `forceDelete` propage la
 * suppression aux dossiers liés (FK cascadeOnDelete : matching, entretiens,
 * contrat, admission…). Idempotent, exécuté quotidiennement.
 */
class PurgerCorbeilleCandidats extends Command
{
    protected $signature = 'candidats:purger-corbeille';

    protected $description = 'Supprime définitivement les candidats en corbeille depuis plus de 30 jours';

    public function handle(): int
    {
        $limite = now()->subDays(Candidate::DELAI_PURGE_JOURS);

        $candidats = Candidate::onlyTrashed()
            ->where('deleted_at', '<=', $limite)
            ->get();

        $candidats->each(fn (Candidate $candidate) => $candidate->forceDelete());

        $this->info($candidats->count().' candidat(s) purgé(s) définitivement.');

        return self::SUCCESS;
    }
}
