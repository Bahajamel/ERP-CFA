<?php

namespace App\Filament\Resources\CfaMissions\Pages;

use App\Filament\Resources\CfaMissions\CfaMissionResource;
use App\Filament\Widgets\CfaMissionsCouvertureWidget;
use Filament\Resources\Pages\ListRecords;

class ListCfaMissions extends ListRecords
{
    protected static string $resource = CfaMissionResource::class;

    public function getSubheading(): ?string
    {
        return 'Tout CFA a 14 missions légales (article L6231-2 du Code du travail). Ce registre '
            .'suit, pour chaque mission, les livrables qui la prouvent — générés par LivretRS ou '
            .'déposés à la main. Objectif : pouvoir démontrer à tout moment que chaque mission est '
            .'couverte. Le badge orange du menu = nombre de missions sans aucun livrable rattaché.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CfaMissionsCouvertureWidget::class,
        ];
    }

    // Référentiel figé (L6231-2) : aucune création depuis l'UI.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
