<?php

namespace App\Filament\Resources\ServiceFaits;

use App\Filament\Resources\ServiceFaits\Pages\ListServiceFaits;
use App\Filament\Resources\ServiceFaits\Tables\ServiceFaitsTable;
use App\Models\ServiceFait;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ServiceFaitResource extends Resource
{
    protected static ?string $model = ServiceFait::class;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    public static function canCreate(): bool
    {
        return false; // création via l'action « Valider un mois »
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Service fait';

    protected static ?string $modelLabel = 'service fait';

    protected static ?string $pluralModelLabel = 'services faits';

    public static function table(Table $table): Table
    {
        return ServiceFaitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceFaits::route('/'),
        ];
    }
}
