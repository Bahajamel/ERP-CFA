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

it("crée bien les 10 rôles métier et les permissions attendues", function () {
    // 10 rôles CFA (matrice CDC §20) + le rôle « Éditeur » (exploitant de la
    // solution), hors matrice ; 16 permissions de module + « access_editeur »
    // + 8 permissions granulaires « tables personnalisées » (façon Monday) = 25.
    expect(\Spatie\Permission\Models\Role::count())->toBe(11)
        ->and(\Spatie\Permission\Models\Permission::count())->toBe(25);
});

it("tient le rôle Éditeur hors de la matrice des rôles CFA", function () {
    // Garde-fou du modèle SaaS : « Administrateur » => '*' ouvre tous les modules,
    // mais jamais le pouvoir éditeur (créer/suspendre des CFA).
    expect(makeUser(['Administrateur'])->can(RolePermissionSeeder::PERMISSION_EDITEUR))->toBeFalse()
        ->and(makeUser([RolePermissionSeeder::ROLE_EDITEUR])->can(RolePermissionSeeder::PERMISSION_EDITEUR))->toBeTrue();
});

it('filtre les modules selon le rôle Commercial', function () {
    $this->actingAs(makeUser(['Commercial']));

    expect(\App\Filament\Resources\Candidates\CandidateResource::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\Companies\CompanyResource::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\OpcoFiles\OpcoFileResource::canAccess())->toBeFalse()
        ->and(\App\Filament\Resources\Users\UserResource::canAccess())->toBeFalse();
});

it("donne à l'Administratif l'accès aux contrats et à l'OPCO, pas aux candidats", function () {
    $this->actingAs(makeUser(['Administratif']));

    expect(\App\Filament\Resources\Contracts\ContractResource::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\OpcoFiles\OpcoFileResource::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\Candidates\CandidateResource::canAccess())->toBeFalse();
});

it('rend les référentiels visibles aux bons départements', function () {
    // Formations : catalogue consulté par le commercial, la pédagogie, l'admission, la scolarité
    $this->actingAs(makeUser(['Commercial']));
    expect(\App\Filament\Resources\Formations\FormationResource::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\OpcoFiles\OpcoFileResource::canAccess())->toBeFalse();

    $this->actingAs(makeUser(['Pédagogie']));
    expect(\App\Filament\Resources\Formations\FormationResource::canAccess())->toBeTrue();

    // Dossiers OPCO (access_opco) : administratif & finance
    $this->actingAs(makeUser(['Administratif']));
    expect(\App\Filament\Resources\OpcoFiles\OpcoFileResource::canAccess())->toBeTrue();

    $this->actingAs(makeUser(['Finance']));
    expect(\App\Filament\Resources\OpcoFiles\OpcoFileResource::canAccess())->toBeTrue()
        ->and(\App\Filament\Resources\Formations\FormationResource::canAccess())->toBeFalse();
});

it('crée des comptes de démo connectables (un par rôle, mot de passe « password »)', function () {
    $this->seed(\Database\Seeders\DemoAccountsSeeder::class);

    $commercial = User::where('email', 'commercial@cfa-v2s.fr')->first();

    expect($commercial)->not->toBeNull()
        ->and($commercial->is_active)->toBeTrue()
        ->and($commercial->hasRole('Commercial'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('password', $commercial->password))->toBeTrue();
});
