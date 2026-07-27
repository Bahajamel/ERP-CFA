<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    /** Page « Modifier » premium : chrome custom (carte résumé + barre sticky) enveloppant le formulaire Filament. */
    protected string $view = 'filament.resources.companies.pages.edit-company';

    /**
     * Sur la page « Modifier », le formulaire occupe la colonne de droite : on
     * l'affiche en colonne unique pour des sections pleine largeur. Le schéma
     * partagé (CompanyForm) reste intact — la page « Créer » garde ses 2 colonnes.
     */
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->columns(1);
    }

    /**
     * Données réelles de la carte résumé (aucune donnée fictive) : identité,
     * statut partenaire, secteur, SIRET, OPCO, contact principal, dernière MAJ
     * et activité — le formulaire lui-même reste 100 % Filament.
     */
    protected function getViewData(): array
    {
        $c = $this->getRecord();

        return [
            'resume' => [
                'secteur' => $c->secteur,
                'siret' => $c->siret,
                'opco' => $c->opco?->nom,
                'contact' => $c->contactPrincipal()->first() ?? $c->contacts()->first(),
                'majLe' => $c->updated_at,
                'besoinsOuverts' => $c->needs()->ouverts()->count(),
                'contrats' => $c->contracts()->count(),
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
