<?php

namespace App\Filament\Resources\OpcoFiles\Tables;

use App\Enums\OpcoStatut;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\OpcoFiles\OpcoFileActions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OpcoFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['opco', 'contract.candidate', 'contract.company.opco'])
                // Masque les dossiers dont le candidat a été supprimé (corbeille).
                ->whereHas('contract.candidate'))
            ->columns([
                TextColumn::make('contract.candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record) => $record->contract?->candidate?->nom_complet)
                    ->description(fn ($record) => $record->contract?->company?->raison_sociale)
                    ->searchable(),
                TextColumn::make('opco.nom')
                    ->label('OPCO')
                    ->state(fn ($record) => $record->opcoEffectif()?->nom)
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('montant_prevu')
                    ->label('Montant prévu')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('montant_accepte')
                    ->label('Montant accepté')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('montant_verse')
                    ->label('Versé')
                    ->state(fn ($record) => $record->montantVerse())
                    ->money('EUR')
                    ->color('success')
                    ->toggleable(),
                TextColumn::make('reste_a_verser')
                    ->label('Reste à verser')
                    ->state(fn ($record) => $record->resteAVerser())
                    ->money('EUR')
                    ->color(fn ($record) => $record->resteAVerser() > 0 ? 'warning' : 'gray')
                    ->toggleable(),
                TextColumn::make('date_depot')
                    ->label('Déposé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(OpcoStatut::class),
                Filter::make('bloques')
                    ->label('Dossiers bloqués')
                    ->query(fn (Builder $query) => $query->whereIn('statut', OpcoStatut::bloques())),
            ])
            ->recordActions([
                OpcoFileActions::preparerDepot(),
                OpcoFileActions::accepter(),
                OpcoFileActions::rejeter(),
                // Lien vers l'admission ouverte automatiquement (cycle apprenant).
                Action::make('voirAdmission')
                    ->label('Voir l\'admission')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('info')
                    ->visible(fn ($record): bool => $record->contract?->admission !== null)
                    ->url(fn ($record): string => AdmissionResource::getUrl(
                        'edit',
                        ['record' => $record->contract->admission],
                    )),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('Aucun dossier OPCO')
            ->emptyStateDescription('Les dossiers de financement sont créés automatiquement à la transmission d\'un contrat. Signez un contrat pour démarrer.');
    }
}
