<?php

namespace App\Filament\RelationManagers;

use App\Enums\NoteType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Gestionnaire de notes internes polymorphe et réutilisable (P0-03-5).
 * Se branche sur n'importe quelle ressource dont le modèle expose une relation
 * « notes » (entreprise, besoin, candidat…). Gère notes, comptes rendus,
 * incidents et mesures de satisfaction, avec auteur et horodatage automatiques.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes & suivi';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chat-bubble-left-right';

    /**
     * Filament passe les RelationManagers en lecture seule sur une page de
     * consultation (ViewRecord), ce qui masque les actions Créer / Modifier /
     * Supprimer. On les réactive pour pouvoir ajouter notes et comptes rendus
     * directement depuis la fiche (entreprise, candidat, besoin…).
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /** Le type sélectionné est-il « Satisfaction » ? (tolère enum ou string). */
    protected static function isSatisfaction(mixed $type): bool
    {
        return in_array($type, [NoteType::Satisfaction, NoteType::Satisfaction->value], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Type')
                    ->options(NoteType::class)
                    ->default(NoteType::Note->value)
                    ->live()
                    ->required(),
                Select::make('satisfaction')
                    ->label('Niveau de satisfaction')
                    ->options([
                        1 => '1 — Très insatisfait',
                        2 => '2 — Insatisfait',
                        3 => '3 — Neutre',
                        4 => '4 — Satisfait',
                        5 => '5 — Très satisfait',
                    ])
                    ->visible(fn (Get $get): bool => self::isSatisfaction($get('type')))
                    ->required(fn (Get $get): bool => self::isSatisfaction($get('type'))),
                Textarea::make('contenu')
                    ->label('Contenu')
                    ->rows(4)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contenu')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('contenu')
                    ->label('Contenu')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('satisfaction')
                    ->label('Satisfaction')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state}/5" : '—')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('author.name')
                    ->label('Auteur')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(NoteType::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une note')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
