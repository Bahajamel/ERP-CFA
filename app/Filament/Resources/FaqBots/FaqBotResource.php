<?php

namespace App\Filament\Resources\FaqBots;

use App\Filament\Resources\FaqBots\Pages\EditFaqBot;
use App\Filament\Resources\FaqBots\Pages\ListFaqBots;
use App\Filament\Resources\FaqBots\RelationManagers\EntreesRelationManager;
use App\Models\FaqBot;
use App\Support\Assistant\AssistantContexte;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Gestion des assistants FAQ : un assistant par grande partie du logiciel, avec
 * son identité (nom, teinte, icône, avatar, message d'accueil) et ses
 * questions/réponses — le tout modifiable sans toucher au code.
 */
class FaqBotResource extends Resource
{
    protected static ?string $model = FaqBot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Assistants FAQ';

    protected static ?string $modelLabel = 'assistant FAQ';

    protected static ?string $pluralModelLabel = 'assistants FAQ';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('access_users') ?? false;
    }

    /** Les assistants sont livrés par le seeder : on les modifie, on n'en crée pas. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité de l\'assistant')
                ->description('Ce que voit l\'utilisateur : le nom, la teinte et l\'avatar du bouton d\'aide.')
                ->icon('heroicon-o-sparkles')
                ->columns(2)
                ->schema([
                    Select::make('module')
                        ->label('Partie du logiciel')
                        ->options(AssistantContexte::options())
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Détermine les pages où cet assistant apparaît.'),
                    TextInput::make('name')
                        ->label('Nom affiché')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('description')
                        ->label('Sous-titre')
                        ->placeholder('ex : Candidats, entreprises, offres…')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    ColorPicker::make('color')
                        ->label('Couleur')
                        ->required()
                        ->helperText('Teinte du bouton, de l\'en-tête et des bulles.'),
                    TextInput::make('icon')
                        ->label('Icône (Heroicon)')
                        ->placeholder('heroicon-o-user-group')
                        ->helperText('Utilisée si aucun avatar n\'est fourni.'),
                    FileUpload::make('avatar_path')
                        ->label('Avatar')
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->disk(FaqBot::DISQUE_AVATARS)
                        ->directory(FaqBot::DOSSIER_AVATARS)
                        ->maxSize(2048)
                        ->helperText('Facultatif : à défaut, l\'icône ci-dessus est affichée.')
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Actif')
                        ->helperText('Décochez pour retirer cet assistant des pages concernées.'),
                ]),

            Section::make('Message d\'accueil')
                ->description('Première phrase affichée à l\'ouverture du chat.')
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->schema([
                    Textarea::make('welcome_message')
                        ->hiddenLabel()
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('')
                    ->circular()
                    ->disk(FaqBot::DISQUE_AVATARS)
                    ->defaultImageUrl(fn (): ?string => null),
                TextColumn::make('name')
                    ->label('Assistant')
                    ->weight('bold')
                    ->description(fn (FaqBot $record): ?string => $record->description)
                    ->searchable(),
                TextColumn::make('module')
                    ->label('Partie')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => AssistantContexte::options()[$state] ?? (string) $state),
                ColorColumn::make('color')->label('Couleur'),
                TextColumn::make('entrees_count')
                    ->label('Réponses')
                    ->counts('entrees')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make()->label('Modifier'),
            ])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('Aucun assistant installé')
            ->emptyStateDescription('Lancez « php artisan db:seed --class=FaqBotSeeder » pour installer les assistants et leur FAQ de départ.');
    }

    public static function getRelations(): array
    {
        return [
            EntreesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqBots::route('/'),
            'edit' => EditFaqBot::route('/{record}/edit'),
        ];
    }
}
