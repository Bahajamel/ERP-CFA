<?php

namespace App\Filament\Resources\Matchings\Tables;

use App\Enums\MatchingStatut;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
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
use Illuminate\Database\Eloquent\Builder;

class MatchingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Masque les lignes dont le candidat a été supprimé (corbeille) :
            // plus de matching orphelin « sans nom » dans le tableau.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('candidate'))
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('need.intitule_poste')
                    ->label('Besoin')
                    ->description(fn ($record) => $record->need?->company?->raison_sociale)
                    ->placeholder('Recherche en cours — aucune entreprise rattachée')
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
            // Le contrat naît automatiquement à l'acceptation du matching :
            // l'action sert surtout de lien direct vers le dossier lié.
            ->label(fn (Matching $record): string => self::contratExistant($record)
                ? 'Voir le contrat'
                : 'Créer le contrat')
            ->icon('heroicon-o-document-plus')
            ->color('success')
            ->visible(fn (Matching $record): bool => $record->statut === MatchingStatut::Accepte)
            ->requiresConfirmation()
            ->modalHeading(fn (Matching $record): string => self::contratExistant($record)
                ? 'Ouvrir le contrat d\'apprentissage'
                : 'Créer le contrat d\'apprentissage')
            ->modalDescription(fn (Matching $record): string => self::contratExistant($record)
                ? 'Le contrat créé automatiquement à l\'acceptation du matching sera ouvert.'
                : 'Un contrat prérempli sera créé pour '
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

    /** Un contrat actif existe-t-il déjà pour ce candidat × entreprise ? */
    private static function contratExistant(Matching $record): bool
    {
        if ($record->need?->company_id === null || $record->candidate_id === null) {
            return false;
        }

        return Contract::query()
            ->where('candidate_id', $record->candidate_id)
            ->where('company_id', $record->need->company_id)
            ->whereNotIn('statut_contrat', array_map(
                fn ($s) => $s->value,
                CycleApprenant::CONTRATS_ACTIFS_EXCLUS,
            ))
            ->exists();
    }
}
