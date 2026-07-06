<?php

namespace App\Observers;

use App\Enums\AdmissionStatut;
use App\Models\Candidate;

class CandidateObserver
{
    /**
     * À la création d'un candidat, ouvre automatiquement son dossier de
     * pré-admission (idempotent : un seul dossier par candidat). Aucune checklist
     * de documents n'est générée — à cette étape, seul le CV compte, et il est
     * porté par le candidat lui-même.
     */
    public function created(Candidate $candidate): void
    {
        $candidate->admission()->firstOrCreate([], [
            'statut' => AdmissionStatut::AVerifier->value,
        ]);
    }
}
