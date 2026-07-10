<?php

namespace App\Filament\Exports;

use App\Models\Need;
use Filament\Actions\Exports\ExportColumn;

class NeedExporter extends BaseExporter
{
    protected static ?string $model = Need::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('intitule_poste')->label('Poste'),
            ExportColumn::make('company.raison_sociale')->label('Entreprise'),
            ExportColumn::make('formation.libelle')->label('Formation'),
            ExportColumn::make('localisation')->label('Localisation'),
            ExportColumn::make('nb_postes')->label('Nb postes'),
            ExportColumn::make('rythme')->label('Rythme'),
            ExportColumn::make('statut')->label('Statut'),
            ExportColumn::make('date_demarrage')->label('Démarrage'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }
}
