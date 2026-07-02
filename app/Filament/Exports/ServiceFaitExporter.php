<?php

namespace App\Filament\Exports;

use App\Models\ServiceFait;
use Filament\Actions\Exports\ExportColumn;

class ServiceFaitExporter extends BaseExporter
{
    protected static ?string $model = ServiceFait::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('promotion.libelle')->label('Classe'),
            ExportColumn::make('annee')->label('Année'),
            ExportColumn::make('mois')->label('Mois'),
            ExportColumn::make('nb_seances')->label('Nb séances'),
            ExportColumn::make('nb_heures')->label('Heures'),
            ExportColumn::make('taux_presence')->label('Assiduité (%)'),
            ExportColumn::make('validatedBy.name')->label('Validé par'),
            ExportColumn::make('validated_at')->label('Validé le'),
        ];
    }
}
