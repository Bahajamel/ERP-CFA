<?php

namespace App\Filament\Exports;

use App\Models\Candidate;
use Filament\Actions\Exports\ExportColumn;

class CandidateExporter extends BaseExporter
{
    protected static ?string $model = Candidate::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nom')->label('Nom'),
            ExportColumn::make('prenom')->label('Prénom'),
            ExportColumn::make('email')->label('Email'),
            ExportColumn::make('telephone')->label('Téléphone'),
            ExportColumn::make('statut')->label('Statut'),
            ExportColumn::make('formationVisee.libelle')->label('Formation visée'),
            ExportColumn::make('niveau_actuel')->label('Niveau actuel'),
            ExportColumn::make('mobilite')->label('Mobilité'),
            ExportColumn::make('disponibilite')->label('Disponibilité'),
            ExportColumn::make('source')->label('Source'),
            ExportColumn::make('commercial.name')->label('Commercial'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }
}
