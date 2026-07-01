<?php

namespace App\Filament\Resources\Needs\Tables;

use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Need;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NeedsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('intitule_poste')
                    ->label('Poste')
                    ->description(fn ($record) => $record->company?->raison_sociale)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('localisation')
                    ->label('Lieu')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('nb_postes')
                    ->label('Postes')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('matchings_count')
                    ->label('Candidats proposés')
                    ->counts('matchings')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                TextColumn::make('date_demarrage')
                    ->label('Démarrage')
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
                    ->options(NeedStatut::class),
                SelectFilter::make('company_id')
                    ->label('Entreprise')
                    ->relationship('company', 'raison_sociale')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('trouverCandidats')
                    ->label('Trouver des candidats')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->modalHeading(fn (Need $record): string => "Candidats compatibles — {$record->intitule_poste}")
                    ->modalSubmitActionLabel('Proposer les candidats cochés')
                    ->schema(fn (Need $record): array => [
                        CheckboxList::make('candidates')
                            ->label('Candidats compatibles (classés par score de compatibilité)')
                            ->options(
                                $record->candidatsCompatibles()
                                    ->mapWithKeys(fn (array $row): array => [
                                        $row['candidate']->id => "{$row['candidate']->nom_complet} — {$row['score']} pts · {$row['explication']}",
                                    ])
                                    ->all()
                            )
                            ->helperText('Aucune ligne = aucun candidat compatible (formation, disponibilité…). Coche ceux à proposer.')
                            ->bulkToggleable()
                            ->columns(1),
                    ])
                    ->action(function (Need $record, array $data): void {
                        $created = 0;

                        foreach ($data['candidates'] ?? [] as $candidateId) {
                            if (! $record->matchings()->where('candidate_id', $candidateId)->exists()) {
                                $record->matchings()->create([
                                    'candidate_id' => $candidateId,
                                    'statut' => MatchingStatut::Propose,
                                    'assigned_by' => auth()->id(),
                                ]);
                                $created++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title($created > 0 ? "{$created} candidat(s) proposé(s)" : 'Aucun candidat proposé')
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
