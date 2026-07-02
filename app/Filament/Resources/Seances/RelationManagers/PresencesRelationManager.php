<?php

namespace App\Filament\Resources\Seances\RelationManagers;

use App\Enums\PresenceStatut;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
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
            ->recordActions([
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
