<?php

namespace App\Filament\Exports;

use App\Models\FinanceLine;
use Filament\Actions\Exports\ExportColumn;

class FinanceLineExporter extends BaseExporter
{
    protected static ?string $model = FinanceLine::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('contract.code_rncp')->label('Contrat (RNCP)'),
            ExportColumn::make('libelle')->label('Libellé'),
            ExportColumn::make('montant_attendu')->label('Montant attendu'),
            ExportColumn::make('montant_accepte')->label('Montant accepté'),
            ExportColumn::make('montant_bloque')->label('Montant bloqué'),
            ExportColumn::make('motif_blocage')->label('Motif de blocage'),
            ExportColumn::make('created_at')->label('Créée le'),
        ];
    }
}
