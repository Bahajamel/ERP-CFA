<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    /** Page « Nouvelle entreprise » premium : chrome custom (résumé du dossier + barre sticky) enveloppant le formulaire Filament. */
    protected string $view = 'filament.resources.companies.pages.create-company';

    /**
     * Sections réelles du formulaire de création (pour la carte « Résumé du
     * dossier ») — reflètent exactement CompanyForm. Les contacts, tuteurs et
     * besoins se gèrent après création via leurs onglets (RelationManagers).
     *
     * @return array<int, array{label: string, icon: string}>
     */
    protected function getViewData(): array
    {
        return [
            'sections' => [
                ['label' => 'Entreprise', 'icon' => 'building'],
                ['label' => 'Adresse', 'icon' => 'pin'],
            ],
        ];
    }
}
