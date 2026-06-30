<?php

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeUser(array $roles = [], bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);

    if ($roles !== []) {
        $user->syncRoles($roles);
    }

    return $user;
}

it("refuse l'accès au panel à un compte sans rôle", function () {
    $panel = Filament::getPanel('admin');

    expect(makeUser([])->canAccessPanel($panel))->toBeFalse();
});

it("refuse l'accès au panel à un compte désactivé", function () {
    $panel = Filament::getPanel('admin');

    expect(makeUser(['Commercial'], active: false)->canAccessPanel($panel))->toBeFalse();
});

it("autorise l'accès au panel à un compte actif avec un rôle", function () {
    $panel = Filament::getPanel('admin');

    expect(makeUser(['Commercial'])->canAccessPanel($panel))->toBeTrue();
});

it("réserve la gestion des utilisateurs à l'administrateur", function () {
    $this->actingAs(makeUser(['Administrateur']));
    expect(UserResource::canAccess())->toBeTrue();

    $this->actingAs(makeUser(['Commercial']));
    expect(UserResource::canAccess())->toBeFalse();
});

it('applique la matrice des permissions par rôle', function () {
    $commercial = makeUser(['Commercial']);
    expect($commercial->can('access_candidates'))->toBeTrue()
        ->and($commercial->can('access_finance'))->toBeFalse();

    $finance = makeUser(['Finance']);
    expect($finance->can('access_finance'))->toBeTrue()
        ->and($finance->can('access_candidates'))->toBeFalse();
});

it("crée bien les 10 rôles et 15 permissions", function () {
    expect(\Spatie\Permission\Models\Role::count())->toBe(10)
        ->and(\Spatie\Permission\Models\Permission::count())->toBe(15);
});
