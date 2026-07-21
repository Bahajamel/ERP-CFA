<?php

namespace App\Filament\Resources\FaqBots\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Questions/réponses d'un assistant : ajout, modification, activation et
 * réordonnancement par glisser-déposer (l'ordre décide aussi des suggestions
 * proposées d'emblée dans le chat).
 */
class EntreesRelationManager extends RelationManager
{
    protected static string $relationship = 'toutesLesEntrees';

    protected static ?string $title = 'Questions / réponses';

    protected static ?string $modelLabel = 'question';

    protected static ?string $pluralModelLabel = 'questions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('question')
                        ->label('Question')
                        ->placeholder('ex : Comment créer un candidat ?')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('answer')
                        ->label('Réponse')
                        ->rows(4)
                        ->required()
                        ->columnSpanFull(),
                    TagsInput::make('keywords')
                        ->label('Mots-clés')
                        ->placeholder('Ajouter un mot-clé…')
                        ->helperText('Aident à retrouver la réponse même si la question est formulée autrement.')
                        ->columnSpanFull(),
                    TextInput::make('category')
                        ->label('Catégorie')
                        ->placeholder('ex : Candidats')
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

            Section::make('Lien vers une page (facultatif)')
                ->description('Ajoute un bouton sous la réponse. Le lien est masqué si l\'utilisateur n\'a pas la permission indiquée.')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextInput::make('link_route')
                        ->label('Nom de route')
                        ->placeholder('filament.admin.resources.candidates.create'),
                    TextInput::make('link_label')
                        ->label('Libellé du bouton')
                        ->placeholder('Ajouter un candidat'),
                    TextInput::make('link_permission')
                        ->label('Permission requise')
                        ->placeholder('access_candidates'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Question')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            // L'ordre pilote aussi les suggestions affichées à l'ouverture du chat.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->label('Ajouter une question'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucune question pour cet assistant')
            ->emptyStateDescription('Ajoutez les questions fréquentes de cette partie du logiciel.');
    }
}
