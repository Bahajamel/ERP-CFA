<?php

namespace App\Filament\Exports;

use App\Models\Company;
use Filament\Actions\Exports\ExportColumn;

class CompanyExporter extends BaseExporter
{
    protected static ?string $model = Company::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('raison_sociale')->label('Raison sociale'),
            ExportColumn::make('nom_commercial')->label('Nom commercial'),
            ExportColumn::make('siret')->label('SIRET'),
            ExportColumn::make('secteur')->label('Secteur'),
            ExportColumn::make('statut')->label('Statut'),
            ExportColumn::make('adresse')->label('Adresse'),
            ExportColumn::make('created_at')->label('Créée le'),
        ];
    }
}
