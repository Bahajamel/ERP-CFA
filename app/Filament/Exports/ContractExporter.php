<?php

namespace App\Filament\Exports;

use App\Models\Contract;
use Filament\Actions\Exports\ExportColumn;

class ContractExporter extends BaseExporter
{
    protected static ?string $model = Contract::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('candidate.nom')->label('Apprenti (nom)'),
            ExportColumn::make('candidate.prenom')->label('Apprenti (prénom)'),
            ExportColumn::make('company.raison_sociale')->label('Entreprise'),
            ExportColumn::make('formation.libelle')->label('Formation'),
            ExportColumn::make('code_rncp')->label('Code RNCP'),
            ExportColumn::make('date_debut')->label('Début'),
            ExportColumn::make('date_fin')->label('Fin'),
            ExportColumn::make('rythme')->label('Rythme'),
            ExportColumn::make('lieu_formation')->label('Lieu de formation'),
            ExportColumn::make('statut_signature')->label('Signature'),
            ExportColumn::make('statut_contrat')->label('Statut du contrat'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }
}
