<?php

namespace App\Filament\Resources\Entretiens;

use App\Filament\Resources\Entretiens\Pages\CalendrierEntretiens;
use App\Filament\Resources\Entretiens\Pages\CreateEntretien;
use App\Filament\Resources\Entretiens\Pages\EditEntretien;
use App\Filament\Resources\Entretiens\Pages\ListEntretiens;
use App\Filament\Resources\Entretiens\Schemas\EntretienForm;
use App\Filament\Resources\Entretiens\Tables\EntretiensTable;
use App\Models\Entretien;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Section Entretiens : planification et suivi des entretiens candidats,
 * séparés du dossier candidat. La décision (accepter / refuser) prise ici
 * fait avancer automatiquement le cycle apprenant.
 */
class EntretienResource extends Resource
{
    protected static ?string $model = Entretien::class;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_candidates');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Entretiens';

    protected static ?string $modelLabel = 'entretien';

    protected static ?string $pluralModelLabel = 'entretiens';

    public static function form(Schema $schema): Schema
    {
        return EntretienForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntretiensTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntretiens::route('/'),
            'calendrier' => CalendrierEntretiens::route('/calendrier'),
            'create' => CreateEntretien::route('/create'),
            'edit' => EditEntretien::route('/{record}/edit'),
        ];
    }
}
