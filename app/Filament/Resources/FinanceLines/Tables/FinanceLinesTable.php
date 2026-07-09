<?php

namespace App\Filament\Resources\FinanceLines\Tables;

use App\Enums\FinanceLineStatut;
use App\Enums\InvoiceStatut;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\FinanceLines\Schemas\FinanceLineForm;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Models\FinanceLine;
use App\Services\FinanceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceLinesTable
{
    /** Formatage monétaire « 7 200,00 € ». */
    public static function euros(float|string|null $montant): string
    {
        return FinanceService::euros($montant);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'contract.candidate', 'invoices.payments', 'opcoFile.payments', 'opcoFile.opco', 'opcoFile.contract.company.opco',
            ]))
            ->columns([
                TextColumn::make('contract')
                    ->label('Contrat')
                    ->state(fn (FinanceLine $record): string => $record->contract
                        ? FinanceLineForm::libelleContrat($record->contract)
                        : '—')
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'contract.candidate',
                        fn (Builder $q) => $q->where('nom', 'like', "%{$search}%")->orWhere('prenom', 'like', "%{$search}%"),
                    )),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->state(fn (FinanceLine $record): FinanceLineStatut => $record->statut()),
                TextColumn::make('montant_attendu')
                    ->label('Attendu')
                    ->formatStateUsing(fn ($state): string => self::euros($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('facture')
                    ->label('Facturé')
                    ->state(fn (FinanceLine $record): string => self::euros($record->montantFacture()))
                    ->alignEnd(),
                TextColumn::make('encaisse')
                    ->label('Encaissé')
                    ->state(fn (FinanceLine $record): string => self::euros($record->montantEncaisse()))
                    ->color('success')
                    ->alignEnd(),
                TextColumn::make('reste')
                    ->label('Reste à encaisser')
                    ->state(fn (FinanceLine $record): string => self::euros($record->resteAEncaisser()))
                    ->badge()
                    ->color(fn (FinanceLine $record): string => $record->resteAEncaisser() > 0 ? 'warning' : 'success')
                    ->alignEnd(),
                TextColumn::make('montant_bloque')
                    ->label('Bloqué')
                    ->formatStateUsing(fn ($state): string => self::euros($state))
                    ->badge()
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'gray')
                    ->tooltip(fn (FinanceLine $record): ?string => $record->motif_blocage)
                    ->alignEnd()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('contract_id')
                    ->label('Contrat')
                    ->relationship('contract', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => FinanceLineForm::libelleContrat($record))
                    ->searchable()
                    ->preload(),
                Filter::make('bloque')
                    ->label('Montant bloqué')
                    ->query(fn (Builder $query): Builder => $query->where('montant_bloque', '>', 0)),
                Filter::make('en_retard')
                    ->label('Facture en retard')
                    ->query(fn (Builder $query): Builder => $query->whereHas('invoices', fn (Builder $q) => $q
                        ->where('statut', InvoiceStatut::Emise->value)
                        ->whereDate('date_echeance', '<', now()))),
            ])
            ->recordActions([
                self::genererFacturesDues(),
                self::verifierCoherence(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('voirContrat')
                        ->label('Voir le contrat')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->visible(fn (FinanceLine $record): bool => $record->contract !== null)
                        ->url(fn (FinanceLine $record): string => ContractResource::getUrl('edit', ['record' => $record->contract_id])),
                    Action::make('voirOpco')
                        ->label('Voir le dossier OPCO')
                        ->icon('heroicon-o-banknotes')
                        ->color('gray')
                        ->visible(fn (FinanceLine $record): bool => $record->opco_file_id !== null)
                        ->url(fn (FinanceLine $record): string => OpcoFileResource::getUrl('edit', ['record' => $record->opco_file_id])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Génère les factures (proforma) des échéances OPCO arrivées à terme et non
     * encore facturées (décret 2025-585). Visible seulement s'il y a du dû.
     */
    private static function genererFacturesDues(): Action
    {
        return Action::make('genererFacturesDues')
            ->label('Générer les factures dues')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->visible(fn (FinanceLine $record): bool => $record->opco_file_id !== null
                && (auth()->user()?->can('access_finance') ?? false)
                && self::aDesEcheancesAFacturer($record))
            ->requiresConfirmation()
            ->modalHeading('Générer les factures dues')
            ->modalDescription('Une facture (brouillon proforma) sera créée pour chaque échéance OPCO arrivée '
                .'à terme et pas encore facturée. Vous pourrez ensuite les émettre depuis la comptabilité.')
            ->action(function (FinanceLine $record): void {
                $creees = app(FinanceService::class)->genererFacturesDues($record, auth()->id());

                if ($creees->isEmpty()) {
                    Notification::make()->warning()->title('Aucune échéance à facturer')
                        ->body('Toutes les échéances arrivées à terme sont déjà facturées.')->send();

                    return;
                }

                Notification::make()->success()
                    ->title($creees->count().' facture(s) générée(s)')
                    ->body('Retrouvez-les dans l\'onglet Factures de la ligne (statut « Brouillon »).')
                    ->send();
            });
    }

    /** Tour de contrôle : cohérence de la ligne (montants, échéances, retards). */
    private static function verifierCoherence(): Action
    {
        return Action::make('verifierCoherence')
            ->label('Vérifier la cohérence')
            ->icon('heroicon-o-shield-check')
            ->color('gray')
            ->modalHeading(fn (FinanceLine $record): string => 'Cohérence — '.$record->libelle)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalContent(fn (FinanceLine $record) => view('filament.finance.coherence', [
                'etat' => app(FinanceService::class)->coherence($record),
            ]));
    }

    /** Reste-t-il au moins une échéance OPCO à terme non facturée ? */
    private static function aDesEcheancesAFacturer(FinanceLine $record): bool
    {
        $dossier = $record->opcoFile;

        if ($dossier === null) {
            return false;
        }

        $service = app(FinanceService::class);

        return $dossier->payments
            ->filter(fn ($p) => $p->date_prevue !== null && ! $p->date_prevue->isFuture())
            ->reject(fn ($p) => $service->echeanceDejaFacturee($p))
            ->isNotEmpty();
    }
}
