<?php

namespace App\Filament\Resources\Matchings\Tables;

use App\Enums\MatchingStatut;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Matching;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MatchingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('need.intitule_poste')
                    ->label('Besoin')
                    ->description(fn ($record) => $record->need?->company?->raison_sociale)
                    ->searchable(),
                IconColumn::make('origine')
                    ->label('Origine')
                    ->icon(fn (Matching $record): string => $record->estOrigineCandidat()
                        ? 'heroicon-o-user'
                        : 'heroicon-o-building-office-2')
                    ->color(fn (Matching $record): string => $record->estOrigineCandidat() ? 'warning' : 'gray')
                    ->tooltip(fn (Matching $record): string => $record->estOrigineCandidat()
                        ? 'Entreprise trouvée par le candidat'
                        : 'Entreprise partenaire (proposée par le CFA)'),
                IconColumn::make('cv_envoye')
                    ->label('CV envoyé')
                    ->boolean(),
                TextColumn::make('date_entretien')
                    ->label('Entretien')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(MatchingStatut::class),
                SelectFilter::make('origine')
                    ->label('Origine')
                    ->options([
                        CycleApprenant::ORIGINE_CFA => 'Entreprise partenaire',
                        CycleApprenant::ORIGINE_CANDIDAT => 'Trouvée par le candidat',
                    ]),
            ])
            ->recordActions([
                self::creerContrat(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading('Aucune proposition en cours')
            ->emptyStateDescription('Proposez un candidat accepté sur un besoin d\'entreprise — le score de compatibilité vous aide à choisir le bon profil.');
    }

    /**
     * Étape suivante du cycle : matching accepté → contrat prérempli
     * (candidat, entreprise, formation, tuteur). Visible uniquement quand le
     * matching est « Accepté » ; anti-doublon géré par le service (le contrat
     * actif existant est rouvert au lieu d'en créer un second).
     */
    private static function creerContrat(): Action
    {
        return Action::make('creerContrat')
            ->label('Créer le contrat')
            ->icon('heroicon-o-document-plus')
            ->color('success')
            ->visible(fn (Matching $record): bool => $record->statut === MatchingStatut::Accepte)
            ->requiresConfirmation()
            ->modalHeading('Créer le contrat d\'apprentissage')
            ->modalDescription(fn (Matching $record): string => 'Un contrat prérempli sera créé pour '
                .($record->candidate?->nom_complet ?? 'ce candidat').' chez '
                .($record->need?->company?->raison_sociale ?? 'l\'entreprise').'.')
            ->action(function (Matching $record) {
                try {
                    $contract = app(CycleApprenant::class)->creerContratDepuisMatching($record);
                } catch (CycleBloqueException $e) {
                    Notification::make()->danger()->title('Création impossible')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($contract->wasRecentlyCreated ? 'Contrat créé et prérempli' : 'Contrat déjà existant')
                    ->body($contract->wasRecentlyCreated
                        ? 'Complétez les dates, le rythme et lancez la signature des trois parties.'
                        : 'Un contrat est déjà en cours pour ce candidat et cette entreprise : il a été ouvert.')
                    ->send();

                return redirect(ContractResource::getUrl('edit', ['record' => $contract]));
            });
    }
}
