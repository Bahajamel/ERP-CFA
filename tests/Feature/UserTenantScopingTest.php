<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** Administrateur du CFA courant. */
function adminDuCfa(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

it('ne liste que les comptes rattachés au CFA courant', function () {
    $this->seed(RolePermissionSeeder::class);

    $autreCfa = Organisation::factory()->create();
    $etranger = User::factory()->create(['name' => 'Compte Autre CFA']);
    $etranger->organisations()->sync([$autreCfa->id]);

    $admin = adminDuCfa(); // rattaché au CFA courant par la factory
    $this->actingAs($admin);

    $noms = UserResource::getEloquentQuery()->pluck('name')->all();

    expect($noms)->toContain($admin->name)
        ->and($noms)->not->toContain('Compte Autre CFA');
});

it('empêche d’ouvrir la fiche d’un compte d’un autre CFA', function () {
    $this->seed(RolePermissionSeeder::class);

    $autreCfa = Organisation::factory()->create();
    $etranger = User::factory()->create();
    $etranger->organisations()->sync([$autreCfa->id]);

    $this->actingAs(adminDuCfa());

    // La résolution du record passe par getEloquentQuery() : introuvable ⇒ 404,
    // même en connaissant l'identifiant.
    $this->get(UserResource::getUrl('edit', ['record' => $etranger]))
        ->assertNotFound();
});

it('rattache automatiquement au CFA courant un compte créé depuis le panneau', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminDuCfa());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Nouvelle Recrue',
            'email' => 'recrue@cfa-test.fr',
            // Doit satisfaire la politique maison (AppServiceProvider) : 12
            // caractères minimum, casse mixte, chiffre ET symbole. La règle
            // `uncompromised()` interroge HaveIBeenPwned : on prend donc une
            // chaîne quelconque, qui n'a aucune chance de figurer dans une fuite.
            'password' => 'Kx7#vTqm-Zr42Ln',
            'roles' => [Role::where('name', 'Administrateur')->value('id')],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recrue = User::where('email', 'recrue@cfa-test.fr')->sole();

    expect($recrue->organisations->pluck('id')->all())
        ->toBe([Filament::getTenant()->id]);
});

it('affiche la liste des utilisateurs sans fuite inter-CFA', function () {
    $this->seed(RolePermissionSeeder::class);

    $autreCfa = Organisation::factory()->create();
    $etranger = User::factory()->create(['name' => 'Zoltan Etranger']);
    $etranger->organisations()->sync([$autreCfa->id]);

    $this->actingAs(adminDuCfa());

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertDontSee('Zoltan Etranger');
});
