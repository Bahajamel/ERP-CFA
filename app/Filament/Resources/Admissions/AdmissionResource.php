<?php

namespace App\Filament\Resources\Admissions;

use App\Filament\Resources\Admissions\Pages\CreateAdmission;
use App\Filament\Resources\Admissions\Pages\EditAdmission;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\Admissions\Schemas\AdmissionForm;
use App\Filament\Resources\Admissions\Tables\AdmissionsTable;
use App\Models\Admission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdmissionResource extends Resource
{
    protected static ?string $model = Admission::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_admissions') ?? false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Admission';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Admissions';

    protected static ?string $modelLabel = 'dossier d\'admission';

    protected static ?string $pluralModelLabel = 'dossiers d\'admission';

    /** Badge de navigation : dossiers encore à vérifier (action attendue). */
    public static function getNavigationBadge(): ?string
    {
        $n = Admission::query()->where('statut', \App\Enums\AdmissionStatut::AVerifier->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Dossiers à vérifier';
    }

    public static function form(Schema $schema): Schema
    {
        return AdmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdmissionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Pré-admission : aucune gestion documentaire ici. Le seul document de
        // cette étape est le CV, porté par le candidat et affiché dans le dossier.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissions::route('/'),
            'create' => CreateAdmission::route('/create'),
            'edit' => EditAdmission::route('/{record}/edit'),
        ];
    }
}
