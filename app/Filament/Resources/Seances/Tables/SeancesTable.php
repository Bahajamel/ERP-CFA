<?php

namespace App\Filament\Resources\Seances\Tables;

use App\Enums\SeanceStatut;
use App\Filament\Actions\FeuilleEmargementAction;
use App\Filament\Actions\FicheEmargementPdfAction;
use App\Filament\Actions\SignaturesEnLigneAction;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('libelle')
                    ->label('Matière')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('formateur.name')
                    ->label('Formateur')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('taux_presence')
                    ->label('Assiduité')
                    ->state(fn (Seance $record): string => ($t = $record->tauxPresence()) === null ? '—' : "{$t} %")
                    ->badge()
                    ->color(fn (Seance $record): string => match (true) {
                        $record->tauxPresence() === null => 'gray',
                        $record->tauxPresence() >= 90 => 'success',
                        $record->tauxPresence() >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                IconColumn::make('feuille_emargement')
                    ->label('Feuille')
                    ->state(fn (Seance $record): bool => $record->feuilleEmargement() !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->tooltip(fn (Seance $record): string => $record->feuilleEmargement()
                        ? 'Feuille d\'émargement archivée'
                        : 'Aucune feuille déposée')
                    ->alignCenter(),
            ])
            ->filters([
                // Filtres combinables : formation puis année → les séances de la cohorte.
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
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(SeanceStatut::class),
            ])
            // Listes déroulantes visibles au-dessus de la liste : on choisit la
            // formation (puis l'année) et on voit directement ses séances.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 4,
            ])
            ->recordActions([
                FicheEmargementPdfAction::make(),
                SignaturesEnLigneAction::make(),
                FeuilleEmargementAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // Même organisation que la liste des classes : séances regroupées
            // par formation (repliable), puis triées par année et par date.
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
