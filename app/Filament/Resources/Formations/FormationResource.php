<?php

namespace App\Filament\Resources\Formations;

use App\Filament\Resources\Formations\Pages\CreateFormation;
use App\Filament\Resources\Formations\Pages\EditFormation;
use App\Filament\Resources\Formations\Pages\ListFormations;
use App\Filament\Resources\Formations\Schemas\FormationForm;
use App\Filament\Resources\Formations\Tables\FormationsTable;
use App\Models\Formation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FormationResource extends Resource
{
    protected static ?string $model = Formation::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_formations') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Formations';

    protected static ?string $modelLabel = 'formation';

    protected static ?string $pluralModelLabel = 'formations';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return FormationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormations::route('/'),
            'create' => CreateFormation::route('/create'),
            'edit' => EditFormation::route('/{record}/edit'),
        ];
    }
}
