<?php

namespace App\Filament\Resources\Candidates\Tables;

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('email')
                    ->label('Contact')
                    ->description(fn ($record) => $record->telephone)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('formationVisee.libelle')
                    ->label('Formation visée')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('commercial.name')
                    ->label('Commercial')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(CandidateStatut::class),
                SelectFilter::make('formation_visee_id')
                    ->label('Formation visée')
                    ->relationship('formationVisee', 'libelle'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('changerStatut')
                    ->label('Changer le statut')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn ($record): bool => filled($record->currentState()->transitions()))
                    ->schema(fn ($record): array => [
                        Select::make('to')
                            ->label('Nouveau statut')
                            ->options(collect($record->currentState()->transitions())
                                ->mapWithKeys(fn (CandidateStatut $s) => [$s->value => $s->getLabel()])
                                ->all())
                            ->required(),
                        Textarea::make('comment')
                            ->label('Commentaire (optionnel)'),
                    ])
                    ->action(function ($record, array $data): void {
                        try {
                            $record->transitionTo(CandidateStatut::from($data['to']), $data['comment'] ?? null);
                            Notification::make()->success()->title('Statut mis à jour')->send();
                        } catch (InvalidTransitionException $e) {
                            Notification::make()->danger()->title('Transition refusée')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('entreprisesACibler')
                    ->label('Entreprises à cibler')
                    ->icon('heroicon-o-building-office-2')
                    ->color('info')
                    ->modalHeading(fn (Candidate $record): string => "Entreprises à cibler — {$record->nom_complet}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(fn (Candidate $record) => view('filament.candidates.entreprises-a-cibler', [
                        'cibles' => $record->entreprisesACibler(),
                    ])),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
