<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Organisation;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // Comptes rattachés aux CFA via une relation many-to-many (organisation_user),
    // pas par organisation_id : le mécanisme d'ownership Filament (qui s'appuie sur
    // une colonne) ne sait pas les cloisonner. Le filtrage est fait à la main dans
    // getEloquentQuery() ci-dessous — sans quoi un CFA verrait les comptes des autres.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'utilisateurs';

    protected static ?int $navigationSort = 1;

    /**
     * Accès réservé aux utilisateurs disposant de la permission « access_users »
     * (rôle Administrateur). Masque la ressource et ses routes pour les autres.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('access_users') ?? false;
    }

    /**
     * Ne montre que les comptes rattachés au CFA courant. S'applique aussi à la
     * résolution des routes (/utilisateurs/{record}/edit) : un CFA ne peut donc
     * pas atteindre le compte d'un autre CFA en devinant son identifiant.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (($tenant = Filament::getTenant()) instanceof Organisation) {
            $query->whereHas(
                'organisations',
                fn (Builder $q) => $q->whereKey($tenant->getKey()),
            );
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
