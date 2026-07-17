<?php

use App\Filament\Editeur\Resources\Organisations\OrganisationResource;
use App\Filament\Editeur\Resources\Organisations\Pages\CreateOrganisation;
use App\Models\Candidate;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Bascule le contexte sur le panneau éditeur (aucun CFA courant). */
function surLePanneauEditeur(): void
{
    Filament::setCurrentPanel('editeur');
    Filament::setTenant(null, isQuiet: true);
}

function compteEditeur(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([RolePermissionSeeder::ROLE_EDITEUR]);

    return $user;
}

function compteAdminCfa(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

it('ouvre le panneau éditeur au rôle Éditeur', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(compteEditeur()->canAccessPanel(Filament::getPanel('editeur')))->toBeTrue();
});

it('ferme le panneau éditeur à un administrateur de CFA', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = compteAdminCfa();

    // Un administrateur possède toutes les permissions de MODULES, mais pas le
    // pouvoir éditeur : garde-fou central du modèle SaaS.
    expect($admin->canAccessPanel(Filament::getPanel('editeur')))->toBeFalse()
        ->and($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('ferme le panneau éditeur à un compte désactivé', function () {
    $this->seed(RolePermissionSeeder::class);

    $editeur = compteEditeur();
    $editeur->update(['is_active' => false]);

    expect($editeur->fresh()->canAccessPanel(Filament::getPanel('editeur')))->toBeFalse();
});

it('n’expose pas la gestion des CFA dans le panneau CFA', function () {
    // La ressource vit sous App\Filament\Editeur : le panneau admin ne la découvre
    // pas (discoverResources ne balaie que App\Filament\Resources).
    $ressourcesAdmin = Filament::getPanel('admin')->getResources();

    expect($ressourcesAdmin)->not->toContain(OrganisationResource::class);
});

it('donne à l’éditeur une vision de tous les CFA', function () {
    $this->seed(RolePermissionSeeder::class);

    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    Filament::setTenant($cfaA, isQuiet: true);
    Candidate::factory()->count(3)->create();
    Filament::setTenant($cfaB, isQuiet: true);
    Candidate::factory()->count(1)->create();

    surLePanneauEditeur();
    $this->actingAs(compteEditeur());

    // Hors contexte CFA, OrganisationScope ne s'applique pas : l'éditeur voit tout.
    $volumetrie = Organisation::query()
        ->withCount('candidates')
        ->pluck('candidates_count', 'id');

    expect($volumetrie[$cfaA->id])->toBe(3)
        ->and($volumetrie[$cfaB->id])->toBe(1);
});

it('sert la liste des CFA à l’éditeur et la refuse à un administrateur de CFA', function () {
    $this->seed(RolePermissionSeeder::class);
    Organisation::factory()->create(['nom' => 'CFA Léonard de Vinci']);

    surLePanneauEditeur();
    $url = OrganisationResource::getUrl('index');

    // Bout en bout (HTTP, middlewares inclus) : l'éditeur entre…
    $this->actingAs(compteEditeur())
        ->get($url)
        ->assertOk()
        ->assertSee('CFA Léonard de Vinci');

    // …l'administrateur d'un CFA se heurte au mur, malgré tous ses modules.
    $this->actingAs(compteAdminCfa())
        ->get($url)
        ->assertForbidden();
});

it('crée un CFA depuis le panneau éditeur', function () {
    $this->seed(RolePermissionSeeder::class);
    surLePanneauEditeur();
    $this->actingAs(compteEditeur());

    Livewire::test(CreateOrganisation::class)
        ->fillForm([
            'nom' => 'CFA Léonard de Vinci',
            'slug' => 'cfa-leonard-de-vinci',
            'actif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Organisation::where('slug', 'cfa-leonard-de-vinci')->exists())->toBeTrue();
});

it('empêche un CFA suspendu de recevoir ses membres', function () {
    $this->seed(RolePermissionSeeder::class);

    $cfa = Organisation::factory()->create(['actif' => true]);
    $membre = User::factory()->create(['is_active' => true]);
    $membre->syncRoles(['Administrateur']);
    $membre->organisations()->syncWithoutDetaching($cfa);

    expect($membre->canAccessTenant($cfa))->toBeTrue();

    $cfa->update(['actif' => false]);

    expect($membre->fresh()->canAccessTenant($cfa->fresh()))->toBeFalse()
        ->and($membre->getTenants(Filament::getPanel('admin'))->pluck('id'))
        ->not->toContain($cfa->id);
});
