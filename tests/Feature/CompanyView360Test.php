<?php

use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Matching;
use App\Models\Need;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);
});

it('agrège les candidats proposés à travers les besoins de l\'entreprise', function () {
    $company = Company::factory()->create();
    $need = Need::factory()->for($company)->create(['statut' => NeedStatut::ProfilsEnvoyes]);
    Matching::factory()->for($need)->count(3)->create(['statut' => MatchingStatut::EnRecherche]);

    // Un autre besoin d'une autre entreprise ne doit pas compter.
    Matching::factory()->create();

    expect($company->matchings()->count())->toBe(3);
});

it('affiche la vue 360° avec besoins, candidats proposés et contrats', function () {
    $company = Company::factory()->create(['raison_sociale' => 'Webtech Solutions']);
    $need = Need::factory()->for($company)->create([
        'intitule_poste' => 'Développeur web alternant',
        'statut' => NeedStatut::CandidatRetenu,
    ]);
    $candidate = Candidate::factory()->create(['nom' => 'Petit', 'prenom' => 'Lucas', 'statut' => \App\Enums\CandidateStatut::Accepte]);
    Matching::factory()->for($need)->create([
        'candidate_id' => $candidate->id,
        'statut' => MatchingStatut::Accepte,
    ]);
    Contract::factory()->create([
        'company_id' => $company->id,
        'candidate_id' => $candidate->id,
    ]);

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->assertOk()
        ->assertSee('Webtech Solutions')
        ->assertSee('Développeur web alternant')
        ->assertSee('Lucas Petit');
});

it('rend la vue sans erreur pour une entreprise sans besoin ni contrat', function () {
    $company = Company::factory()->create();

    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->assertOk()
        ->assertSee('Aucun besoin enregistré pour cette entreprise.');
});
