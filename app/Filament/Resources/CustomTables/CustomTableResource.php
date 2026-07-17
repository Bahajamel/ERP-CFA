<?php

namespace App\Filament\Resources\CustomTables;

use App\Filament\Resources\CustomTables\Pages\CreateCustomTable;
use App\Filament\Resources\CustomTables\Pages\EditCustomTable;
use App\Filament\Resources\CustomTables\Pages\ListCustomTables;
use App\Filament\Resources\CustomTables\RelationManagers\LignesRelationManager;
use App\Models\CustomTable;
use App\Support\CustomFields;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tableaux personnalisés « façon Monday » (Phase 3) : un CFA crée ses propres
 * tables (nom + colonnes), puis y saisit ses lignes (via l'onglet « Lignes »
 * de l'édition). Réservé aux Administrateurs et à la Direction ; isolé par CFA.
 */
class CustomTableResource extends Resource
{
    protected static ?string $model = CustomTable::class;

    public static function canAccess(): bool
    {
        return CustomFields::peutGerer();
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Tableaux personnalisés';

    protected static ?string $modelLabel = 'tableau personnalisé';

    protected static ?string $pluralModelLabel = 'tableaux personnalisés';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le tableau')
                ->icon('heroicon-o-table-cells')
                ->schema([
                    TextInput::make('name')
                        ->label('Nom du tableau')
                        ->placeholder('ex : Suivi partenariats, Événements…')
                        ->required()
                        ->maxLength(255),
                ]),
            Section::make('Colonnes')
                ->icon('heroicon-o-view-columns')
                ->description('Définissez les colonnes de ce tableau (nom + type). Vous saisirez les lignes ensuite.')
                ->schema([
                    // Non stockée sur le modèle : synchronisée dans les pages Create/Edit.
                    CustomFields::repeaterColonnes()->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->label('Tableau')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('colonnes_count')
                    ->label('Colonnes')
                    ->counts('colonnes')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('records_count')
                    ->label('Lignes')
                    ->counts('records')
                    ->badge()
                    ->color('info'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('ouvrir')
                    ->label('Ouvrir')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(fn (CustomTable $record): string => static::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateIcon('heroicon-o-table-cells')
            ->emptyStateHeading('Aucun tableau personnalisé')
            ->emptyStateDescription('Créez votre premier tableau : donnez-lui un nom, définissez ses colonnes, puis saisissez vos lignes.');
    }

    public static function getRelations(): array
    {
        return [
            LignesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomTables::route('/'),
            'create' => CreateCustomTable::route('/create'),
            'edit' => EditCustomTable::route('/{record}/edit'),
        ];
    }
}
