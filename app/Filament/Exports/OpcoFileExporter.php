<?php

namespace App\Filament\Exports;

use App\Models\OpcoFile;
use Filament\Actions\Exports\ExportColumn;

class OpcoFileExporter extends BaseExporter
{
    protected static ?string $model = OpcoFile::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('contract.code_rncp')->label('Contrat (RNCP)'),
            ExportColumn::make('statut')->label('Statut'),
            ExportColumn::make('montant_prevu')->label('Montant prévu'),
            ExportColumn::make('montant_accepte')->label('Montant accepté'),
            ExportColumn::make('motif_rejet')->label('Motif de rejet'),
            ExportColumn::make('date_depot')->label('Déposé le'),
            ExportColumn::make('date_relance')->label('Relance le'),
            ExportColumn::make('responsableCorrection.name')->label('Responsable correction'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }
}
