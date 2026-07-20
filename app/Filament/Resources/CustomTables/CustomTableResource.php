<?php

namespace App\Filament\Resources\CustomTables;

use App\Filament\Resources\CustomTables\Pages\BoardCustomTable;
use App\Filament\Resources\CustomTables\Pages\CreateCustomTable;
use App\Filament\Resources\CustomTables\Pages\EditCustomTable;
use App\Filament\Resources\CustomTables\Pages\KanbanCustomTable;
use App\Filament\Resources\CustomTables\Pages\ListCustomTables;
use App\Filament\Resources\CustomTables\RelationManagers\LignesRelationManager;
use App\Models\CustomTable;
use App\Support\BoardNavigation;
use App\Support\CustomFields;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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
        // Accès gouverné par la permission granulaire (CustomTablePolicy::viewAny) —
        // les capacités création/édition/suppression sont ensuite vérifiées
        // automatiquement par Filament via la policy.
        return Auth::user()?->can('viewAny', CustomTable::class) ?? false;
    }

    /**
     * Liste restreinte aux tableaux accessibles (privé + invitations) : chacun ne
     * voit que les siens et ceux qu'on lui a partagés — la supervision
     * (Administrateur / Direction) voit tout. Cloisonnement CFA appliqué en amont.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->accessiblePar(Auth::user());
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Tableaux personnalisés';

    protected static ?string $modelLabel = 'tableau personnalisé';

    protected static ?string $pluralModelLabel = 'tableaux personnalisés';

    protected static ?string $recordTitleAttribute = 'name';

    /** Icônes proposées pour personnaliser un tableau (heroicons déjà embarqués). */
    public const ICONES = [
        'heroicon-o-table-cells' => 'Tableau',
        'heroicon-o-rectangle-stack' => 'Pile',
        'heroicon-o-users' => 'Personnes',
        'heroicon-o-building-office-2' => 'Entreprise',
        'heroicon-o-briefcase' => 'Mallette',
        'heroicon-o-academic-cap' => 'Formation',
        'heroicon-o-calendar-days' => 'Agenda',
        'heroicon-o-clipboard-document-check' => 'Suivi',
        'heroicon-o-flag' => 'Drapeau',
        'heroicon-o-star' => 'Étoile',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le tableau')
                ->icon('heroicon-o-table-cells')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom du tableau')
                        ->placeholder('ex : Suivi partenariats, Événements…')
                        ->required()
                        ->maxLength(255),
                    Select::make('icon')
                        ->label('Icône')
                        ->options(self::ICONES)
                        ->native(false)
                        ->searchable()
                        ->placeholder('Icône par défaut'),
                    Select::make('context')
                        ->label('Module de rattachement')
                        ->options(BoardNavigation::optionsContexte())
                        ->native(false)
                        ->placeholder('Autonome (Administration)')
                        ->helperText('Rattachez ce tableau à un module pour qu\'il apparaisse dans son sélecteur de tables (ex. plusieurs boards Candidats).'),
                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('À quoi sert ce tableau ?')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    ColorPicker::make('color')
                        ->label('Couleur'),
                    Toggle::make('is_active')
                        ->label('Tableau actif')
                        ->default(true)
                        ->helperText('Décochez pour archiver ce tableau (réversible).'),
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
                    ->icon(fn (CustomTable $record): ?string => $record->icon)
                    ->description(fn (CustomTable $record): ?string => $record->description)
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
                TextColumn::make('context')
                    ->label('Module')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (BoardNavigation::optionsContexte()[$state] ?? $state) : 'Autonome')
                    ->color(fn (?string $state): string => $state ? 'primary' : 'gray')
                    ->toggleable(),
                TextColumn::make('creePar.name')
                    ->label('Créateur')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Actif' : 'Archivé')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs')
                    ->falseLabel('Archivés'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('ouvrir')
                    ->label('Ouvrir')
                    ->icon('heroicon-o-arrow-right-circle')
                    // Ouvre le BOARD (les lignes), pas le formulaire de configuration.
                    ->url(fn (CustomTable $record): string => static::getUrl('board', ['record' => $record])),
                // Archivage réversible (is_active) — distinct de la corbeille (soft delete).
                Action::make('archiver')
                    ->label(fn (CustomTable $record): string => $record->is_active ? 'Archiver' : 'Réactiver')
                    ->icon(fn (CustomTable $record): string => $record->is_active ? 'heroicon-o-archive-box' : 'heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (CustomTable $record): bool => Auth::user()?->can('update', $record) ?? false)
                    ->action(fn (CustomTable $record) => $record->update(['is_active' => ! $record->is_active])),
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
            // Board : affiche les LIGNES du tableau (vue par défaut à l'ouverture).
            'board' => BoardCustomTable::route('/{record}/board'),
            'kanban' => KanbanCustomTable::route('/{record}/kanban'),
            'edit' => EditCustomTable::route('/{record}/edit'),
        ];
    }
}
