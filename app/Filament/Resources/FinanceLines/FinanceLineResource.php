<?php

namespace App\Filament\Resources\FinanceLines;

use App\Filament\Resources\FinanceLines\Pages\CreateFinanceLine;
use App\Filament\Resources\FinanceLines\Pages\EditFinanceLine;
use App\Filament\Resources\FinanceLines\Pages\ListFinanceLines;
use App\Filament\Resources\FinanceLines\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\FinanceLines\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\FinanceLines\Schemas\FinanceLineForm;
use App\Filament\Resources\FinanceLines\Tables\FinanceLinesTable;
use App\Filament\Widgets\FinanceEncaissementChart;
use App\Filament\Widgets\FinanceFacturesChart;
use App\Models\FinanceLine;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FinanceLineResource extends Resource
{
    protected static ?string $model = FinanceLine::class;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_finance');
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Facturation';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Lignes financières';

    protected static ?string $modelLabel = 'ligne financière';

    protected static ?string $pluralModelLabel = 'lignes financières';

    protected static ?string $recordTitleAttribute = 'libelle';

    public static function form(Schema $schema): Schema
    {
        return FinanceLineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinanceLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InvoicesRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            FinanceEncaissementChart::class,
            FinanceFacturesChart::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinanceLines::route('/'),
            'create' => CreateFinanceLine::route('/create'),
            'edit' => EditFinanceLine::route('/{record}/edit'),
        ];
    }
}
