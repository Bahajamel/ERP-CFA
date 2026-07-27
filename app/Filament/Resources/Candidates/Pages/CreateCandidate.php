<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCandidate extends CreateRecord
{
    protected static string $resource = CandidateResource::class;

    /** Page « Nouveau candidat » premium : chrome custom (résumé du dossier + barre sticky) enveloppant le formulaire Filament. */
    protected string $view = 'filament.resources.candidates.pages.create-candidate';

    /**
     * Sections réelles du formulaire (pour la carte « Résumé du dossier ») —
     * reflètent exactement CandidateForm, sans champ inventé.
     *
     * @return array<int, array{label: string, icon: string}>
     */
    protected function getViewData(): array
    {
        return [
            'sections' => [
                ['label' => 'Identité', 'icon' => 'user'],
                ['label' => 'Formation & suivi', 'icon' => 'cap'],
                ['label' => 'Disponibilité', 'icon' => 'calendar'],
                ['label' => 'Consentement RGPD', 'icon' => 'shield'],
                ['label' => 'Adresse', 'icon' => 'pin'],
                ['label' => 'Pièces du candidat', 'icon' => 'doc'],
            ],
        ];
    }
}
