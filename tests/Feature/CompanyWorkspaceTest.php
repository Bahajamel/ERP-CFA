<?php

use App\Enums\CompanyStatut;
use App\Enums\NeedStatut;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Tables\CompaniesTable;
use App\Models\Company;
use App\Models\Need;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function companyWorkspaceAdmin(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

it('rend le workspace entreprises avec ses filtres rapides', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(companyWorkspaceAdmin());
    Company::factory()->count(3)->create(['statut' => CompanyStatut::Prospect]);

    Livewire::test(ListCompanies::class)
        ->assertOk()
        ->assertSee('entreprises à relancer')
        ->assertSee('entreprises avec besoins')
        ->assertSee('partenaires sans besoin');
});

it('bascule le filtre rapide et le désactive au second clic', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(companyWorkspaceAdmin());

    Livewire::test(ListCompanies::class)
        ->assertSet('quickScope', null)
        ->call('setQuickScope', 'sans_besoin')
        ->assertSet('quickScope', 'sans_besoin')
        ->call('setQuickScope', 'sans_besoin')
        ->assertSet('quickScope', null);
});

it('le filtre rapide « sans besoin » ne garde que les entreprises sans besoin ouvert', function () {
    $this->seed(RolePermissionSeeder::class);
    $avecBesoin = Company::factory()->create();
    Need::factory()->create([
        'company_id' => $avecBesoin->id,
        'statut' => NeedStatut::ProfilsRecherches,
    ]);
    Company::factory()->create(); // sans besoin

    $count = Company::query()
        ->tap(fn ($q) => CompaniesTable::appliquerScopeRapide($q, 'sans_besoin'))
        ->count();

    expect($count)->toBe(1);
});

it('n\'affiche le panneau Focus entreprise qu\'après sélection', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(companyWorkspaceAdmin());
    $e = Company::factory()->create(['raison_sociale' => 'Boulangerie Au Bon Pain']);

    Livewire::test(ListCompanies::class)
        ->assertDontSee('Focus entreprise')
        ->set('focusId', $e->id)
        ->assertSee('Focus entreprise')
        ->assertSee('Boulangerie Au Bon Pain')
        ->call('unfocus')
        ->assertSet('focusId', null)
        ->assertDontSee('Focus entreprise');
});

it('calcule des initiales lisibles à partir de la raison sociale', function () {
    $this->seed(RolePermissionSeeder::class);
    $e = Company::factory()->create(['raison_sociale' => 'Boulangerie Au Bon Pain']);

    expect($e->initiales)->toBe('BAB');
});
