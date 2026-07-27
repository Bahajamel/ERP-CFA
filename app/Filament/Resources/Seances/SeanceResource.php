<?php

namespace App\Filament\Resources\Seances;

use App\Filament\Resources\Seances\Pages\CreateSeance;
use App\Filament\Resources\Seances\Pages\EditSeance;
use App\Filament\Resources\Seances\Pages\ListSeances;
use App\Filament\Resources\Seances\RelationManagers\PresencesRelationManager;
use App\Filament\Resources\Seances\Schemas\SeanceForm;
use App\Filament\Resources\Seances\Tables\SeancesTable;
use App\Models\Seance;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SeanceResource extends Resource
{
    protected static ?string $model = Seance::class;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Séances & émargement';

    protected static ?string $modelLabel = 'séance';

    protected static ?string $pluralModelLabel = 'séances';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return SeanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PresencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeances::route('/'),
            'create' => CreateSeance::route('/create'),
            'edit' => EditSeance::route('/{record}/edit'),
        ];
    }
}
