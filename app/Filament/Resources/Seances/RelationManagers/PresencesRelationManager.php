<?php

namespace App\Filament\Resources\Seances\RelationManagers;

use App\Enums\PresenceStatut;
use App\Filament\Actions\FeuilleEmargementAction;
use App\Filament\Actions\SignaturesEnLigneAction;
use App\Models\Candidate;
use App\Models\Seance;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Émargement d'une séance (P1-14-2) : une ligne par apprenti, statut de présence
 * modifiable en un clic (édition inline) ; qualification justifiée/injustifiée
 * via le statut. Les lignes sont générées automatiquement à la création de la séance.
 */
class PresencesRelationManager extends RelationManager
{
    protected static string $relationship = 'presences';

    protected static ?string $title = 'Émargement';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clipboard-document-list';

    /** Rafraîchit la section (bouton feuille) quand le dépôt vient de l'en-tête de la page. */
    protected function getListeners(): array
    {
        return array_merge(parent::getListeners(), ['feuille-emargement-maj' => '$refresh']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('statut')
                ->label('Présence')
                ->options(PresenceStatut::class)
                ->live()
                ->required(),
            Textarea::make('commentaire')
                ->label('Commentaire')
                ->rows(2)
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('justificatif')
                ->label('Justificatif d\'absence')
                ->collection('justificatif')
                ->downloadable()
                ->openable()
                ->helperText('Requis pour justifier une absence.')
                ->visible(fn (Get $get): bool => in_array($get('statut'), [
                    PresenceStatut::AbsentJustifie->value,
                    PresenceStatut::AbsentInjustifie->value,
                ], true))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('candidate.nom')
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn ($record): ?string => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                SelectColumn::make('statut')
                    ->label('Présence')
                    ->options(collect(PresenceStatut::cases())
                        ->mapWithKeys(fn (PresenceStatut $s): array => [$s->value => $s->getLabel()])
                        ->all())
                    ->selectablePlaceholder(false)
                    ->width('16rem'),
                IconColumn::make('justificatif')
                    ->label('Justificatif')
                    ->state(fn ($record): bool => $record->aJustificatif())
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('commentaire')
                    ->label('Commentaire')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Présence')
                    ->options(PresenceStatut::class),
            ])
            // Au plus près des apprenants : gérer qui participe à la séance
            // (options différentes au sein d'une même classe) + déposer la
            // feuille d'émargement signée.
            ->headerActions([
                Action::make('gererParticipants')
                    ->label('Gérer les participants')
                    ->icon('heroicon-o-user-group')
                    ->color('gray')
                    ->modalHeading('Apprenants de la séance')
                    ->modalDescription('Cochez les apprenants concernés par cette matière. Les décochés seront retirés de l\'émargement de cette séance (leurs présences déjà saisies ici sont perdues).')
                    ->fillForm(fn (self $livewire): array => [
                        'participants_ids' => $livewire->getOwnerRecord()->presences()->pluck('candidate_id')->all(),
                    ])
                    ->schema([
                        CheckboxList::make('participants_ids')
                            ->label('')
                            ->options(fn (self $livewire): array => Candidate::query()
                                ->whereHas('promotions', fn ($q) => $q->whereKey($livewire->getOwnerRecord()->promotion_id))
                                ->orderBy('nom')->orderBy('prenom')
                                ->get()
                                ->mapWithKeys(fn (Candidate $c): array => [$c->id => $c->nom_complet])
                                ->all())
                            ->bulkToggleable()
                            ->columns(2)
                            ->noSearchResultsMessage('Aucun apprenant dans cette classe.'),
                    ])
                    ->action(function (array $data, self $livewire): void {
                        $livewire->getOwnerRecord()->composerParticipants($data['participants_ids'] ?? []);

                        Notification::make()->success()->title('Participants mis à jour')->send();
                    })
                    ->modalSubmitActionLabel('Enregistrer'),
                SignaturesEnLigneAction::make()
                    ->record(fn (self $livewire): Seance => $livewire->getOwnerRecord()),
                FeuilleEmargementAction::make()
                    ->record(fn (self $livewire): Seance => $livewire->getOwnerRecord()),
            ])
            ->recordActions([
                EditAction::make('justificatif')
                    ->label('Justificatif')
                    ->icon('heroicon-o-paper-clip')
                    ->modalHeading('Justificatif d\'absence')
                    ->visible(fn ($record): bool => $record->statut?->estAbsence() ?? false)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('justificatif')
                            ->label('Justificatif')
                            ->collection('justificatif')
                            ->downloadable()
                            ->openable()
                            ->helperText('Dépose le certificat / justificatif de l\'absence.'),
                    ]),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('marquerPresent')
                        ->label('Marquer présents')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each(
                            fn ($presence) => $presence->update(['statut' => PresenceStatut::Present]),
                        )),
                ]),
            ])
            ->paginated(false);
    }
}
