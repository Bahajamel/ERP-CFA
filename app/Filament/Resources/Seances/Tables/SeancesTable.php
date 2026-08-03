<?php

namespace App\Filament\Resources\Seances\Tables;

use App\Enums\DocumentType;
use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Filament\Actions\FeuilleEmargementAction;
use App\Filament\Actions\FicheEmargementPdfAction;
use App\Filament\Actions\SignaturesEnLigneAction;
use App\Filament\Pages\CockpitSeance;
use App\Filament\Resources\Seances\SeanceResource;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SeancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Compteurs de présences pré-calculés (une requête, pas N par ligne).
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['promotion.formation', 'formateur'])
                ->withCount([
                    'presences',
                    'presences as presents_count' => fn (Builder $q) => $q->whereIn(
                        'statut',
                        array_map(fn (PresenceStatut $s) => $s->value, PresenceStatut::presents()),
                    ),
                    'presences as renseignees_count' => fn (Builder $q) => $q->where('statut', '!=', PresenceStatut::NonRenseigne->value),
                ]))
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->description(fn (Seance $record): ?string => $record->heure_debut
                        ? Carbon::parse($record->heure_debut)->format('H\hi')
                            .($record->heure_fin ? '–'.Carbon::parse($record->heure_fin)->format('H\hi') : '')
                        : null)
                    ->sortable(),

                TextColumn::make('promotion.formation.libelle')
                    ->label('Formation / Session')
                    ->weight('bold')
                    ->description(fn (Seance $record): ?string => collect([$record->promotion?->nom_complet, $record->libelle])
                        ->filter()
                        ->implode(' · ') ?: null)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('formateur.name')
                    ->label('Formateur')
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('presents_count')
                    ->label('Présence')
                    ->state(fn (Seance $record): string => ($record->presences_count ?? 0) === 0
                        ? '—'
                        : ($record->presents_count ?? 0).' / '.($record->presences_count ?? 0))
                    ->description(function (Seance $record): ?string {
                        $rens = $record->renseignees_count ?? 0;

                        return $rens > 0 ? round(($record->presents_count ?? 0) / $rens * 100).' %' : null;
                    })
                    ->badge()
                    ->color(function (Seance $record): string {
                        $rens = $record->renseignees_count ?? 0;
                        if ($rens === 0) {
                            return 'gray';
                        }
                        $taux = ($record->presents_count ?? 0) / $rens * 100;

                        return match (true) {
                            $taux >= 90 => 'success',
                            $taux >= 70 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->alignCenter(),

                TextColumn::make('statut_affiche')
                    ->label('Statut')
                    ->state(fn (Seance $record): string => $record->statutAffiche()['label'])
                    ->badge()
                    ->color(fn (Seance $record): string => $record->statutAffiche()['color']),

                TextColumn::make('etat_emargement')
                    ->label('Émargement')
                    ->state(fn (Seance $record): string => $record->etatEmargement()['label'])
                    ->badge()
                    ->color(fn (Seance $record): string => $record->etatEmargement()['color']),
            ])
            ->filters([
                SelectFilter::make('formation')
                    ->label('Formation')
                    ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn ($q, $id) => $q
                            ->whereHas('promotion', fn ($p) => $p->where('formation_id', $id)))),
                SelectFilter::make('annee')
                    ->label('Année')
                    ->options(fn (): array => Promotion::query()
                        ->whereNotNull('libelle')
                        ->distinct()
                        ->orderBy('libelle')
                        ->pluck('libelle', 'libelle')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn ($q, $annee) => $q
                            ->whereHas('promotion', fn ($p) => $p->where('libelle', $annee)))),
                SelectFilter::make('promotion_id')
                    ->label('Classe / matière')
                    ->relationship('promotion', 'libelle', fn ($query) => $query->with('formation'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('formateur_id')
                    ->label('Formateur')
                    ->relationship('formateur', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(SeanceStatut::class),
                SelectFilter::make('type_feuille')
                    ->label('Feuille / signatures')
                    ->options([
                        'scan' => 'Scan déposé',
                        'signatures' => 'Signatures en ligne',
                        'aucune' => 'Aucune feuille',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'scan' => $query->whereHas('documents', fn ($q) => $q->where('type', DocumentType::FeuilleEmargement->value)),
                        'signatures' => $query->whereHas('presences', fn ($q) => $q->whereNotNull('signature_token')),
                        'aucune' => $query
                            ->whereDoesntHave('documents', fn ($q) => $q->where('type', DocumentType::FeuilleEmargement->value))
                            ->whereDoesntHave('presences', fn ($q) => $q->whereNotNull('signature_token')),
                        default => $query,
                    }),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('du')->label('Date début')->displayFormat('d/m/Y'),
                        DatePicker::make('au')->label('Date fin')->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                        ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 4,
            ])
            // Clic sur une ligne : ouvre le volet de détail (sans quitter la liste).
            ->recordAction('apercu')
            ->recordActions([
                Action::make('apercu')
                    ->label('Détails')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn (Seance $record): string => 'Séance du '.$record->date->format('d/m/Y'))
                    ->modalContent(fn (Seance $record) => view('filament.seance-detail', [
                        'seance' => $record->loadMissing('promotion.formation', 'formateur', 'presences.candidate'),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->extraModalFooterActions(fn (Seance $record): array => [
                        Action::make('cockpitDepuisDetail')
                            ->label('Émargement en direct')
                            ->icon('heroicon-o-bolt')
                            ->color('primary')
                            ->url(CockpitSeance::getUrl(['seance' => $record->getKey()])),
                        Action::make('modifierDepuisDetail')
                            ->label('Modifier')
                            ->color('gray')
                            ->url(SeanceResource::getUrl('edit', ['record' => $record->getKey()])),
                    ]),

                // Action principale, libellée selon l'état de la séance.
                Action::make('emargementDirect')
                    ->label(fn (Seance $record): string => match ($record->statutAffiche()['label']) {
                        'À compléter' => "Compléter l'émargement",
                        'En cours' => 'Émarger en direct',
                        'Validée', 'Annulée' => "Voir l'émargement",
                        default => "Ouvrir l'émargement",
                    })
                    ->icon('heroicon-o-bolt')
                    ->color('primary')
                    ->url(fn (Seance $record): string => CockpitSeance::getUrl(['seance' => $record->getKey()])),

                ActionGroup::make([
                    FicheEmargementPdfAction::make(),
                    SignaturesEnLigneAction::make(),
                    FeuilleEmargementAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultGroup(
                Group::make('promotion.formation.libelle')
                    ->label('Formation')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            )
            ->groups([
                Group::make('promotion.formation.libelle')
                    ->label('Formation')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
                Group::make('promotion.libelle')
                    ->label('Année')
                    ->collapsible(),
                Group::make('date')
                    ->label('Date')
                    ->date()
                    ->collapsible(),
            ])
            ->defaultSort('date', 'desc');
    }
}
