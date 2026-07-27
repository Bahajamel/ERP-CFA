<?php

namespace App\Filament\Resources\Formations\Pages;

use App\Filament\Resources\Formations\FormationResource;
use App\Models\Formation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

/**
 * Fiche « catalogue » d'une formation : une brochure pédagogique (hero,
 * statistiques et programme en cartes de matières), rendue via une vue de page
 * dédiée (hors infolist, pour occuper toute la largeur), avec navigation d'une
 * formation à l'autre.
 */
class ViewFormation extends ViewRecord
{
    protected static string $resource = FormationResource::class;

    /** Rendu personnalisé plein écran (contourne le conteneur d'infolist). */
    protected string $view = 'filament.resources.formations.view-formation';

    /** Brochure plein écran : on utilise toute la largeur disponible. */
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('formationPrecedente')
                ->label('Formation précédente')
                ->icon('heroicon-o-arrow-left')
                ->iconButton()
                ->color('gray')
                ->visible(fn (): bool => Formation::count() > 1)
                ->url(fn (): string => FormationResource::getUrl('view', ['record' => $this->formationVoisine(-1)])),
            Action::make('formationSuivante')
                ->label('Formation suivante')
                ->icon('heroicon-o-arrow-right')
                ->iconButton()
                ->color('gray')
                ->visible(fn (): bool => Formation::count() > 1)
                ->url(fn (): string => FormationResource::getUrl('view', ['record' => $this->formationVoisine(1)])),
            EditAction::make()->label('Modifier le catalogue'),
        ];
    }

    /**
     * Formation voisine dans le catalogue (ordre alphabétique), avec bouclage :
     * après la dernière on revient à la première, et inversement.
     */
    private function formationVoisine(int $sens): Formation
    {
        $record = $this->getRecord();
        $operateur = $sens > 0 ? '>' : '<';
        $direction = $sens > 0 ? 'asc' : 'desc';

        return Formation::where('libelle', $operateur, $record->libelle)
            ->orderBy('libelle', $direction)
            ->first()
            ?? Formation::orderBy('libelle', $direction)->first()
            ?? $record;
    }
}
