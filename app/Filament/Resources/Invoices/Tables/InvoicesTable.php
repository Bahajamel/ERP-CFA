<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatut;
use App\Filament\Resources\Invoices\InvoiceActions;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['financeLine.contract.candidate', 'financeLine.contract.company', 'payments'])
                // Masque les factures d'un dossier dont le candidat a été supprimé.
                ->whereHas('financeLine.contract.candidate'))
            ->columns([
                TextColumn::make('numero')
                    ->label('N° facture')
                    ->placeholder('— (brouillon)')
                    ->searchable(),
                TextColumn::make('financeLine.contract.candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn (Invoice $record) => $record->financeLine?->contract?->candidate?->nom_complet)
                    ->description(fn (Invoice $record) => $record->financeLine?->contract?->company?->raison_sociale)
                    ->searchable(),
                TextColumn::make('destinataire')
                    ->label('Destinataire')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('montant')
                    ->label('Montant')
                    ->money('EUR')
                    ->description(fn (Invoice $record): ?string => $record->montantPaye() > 0
                        ? 'Encaissé : '.number_format($record->montantPaye(), 2, ',', ' ').' €'
                        : null)
                    ->sortable(),
                TextColumn::make('reste_a_payer')
                    ->label('Reste à payer')
                    ->state(fn (Invoice $record): float => $record->resteAPayer())
                    ->money('EUR')
                    ->color(fn (Invoice $record): string => $record->resteAPayer() > 0 ? 'warning' : 'success'),
                TextColumn::make('date_echeance')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->description(fn (Invoice $record): ?string => $record->estEnRetard() ? 'En retard' : null)
                    ->color(fn (Invoice $record): ?string => $record->estEnRetard() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(InvoiceStatut::class),
                Filter::make('en_retard')
                    ->label('En retard')
                    ->query(fn (Builder $query) => $query
                        ->where('statut', InvoiceStatut::Emise->value)
                        ->whereDate('date_echeance', '<', now())),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(['sm' => 2, 'lg' => 2])
            ->recordActions([
                InvoiceActions::emettre(),
                InvoiceActions::encaisser(),
                InvoiceActions::paiements(),
                InvoiceActions::relancer(),
                InvoiceActions::proforma(),
                InvoiceActions::importer(),
                InvoiceActions::annuler(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('Aucune facture')
            ->emptyStateDescription('Les factures sont générées automatiquement depuis l\'échéancier des dossiers OPCO acceptés.');
    }
}