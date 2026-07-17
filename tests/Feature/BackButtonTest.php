<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Rôle métier (non-admin) avec accès Candidats & Entreprises, sans MFA.
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Commercial');
    $this->actingAs($this->user);
});

it('affiche « Retour à la liste » sur Créer / Modifier / Voir un candidat', function () {
    $candidate = Candidate::factory()->create();

    $this->get(CandidateResource::getUrl('create'))->assertOk()->assertSee('cfa-back-btn', false);
    $this->get(CandidateResource::getUrl('edit', ['record' => $candidate]))->assertOk()->assertSee('cfa-back-btn', false);
    $this->get(CandidateResource::getUrl('view', ['record' => $candidate]))->assertOk()->assertSee('cfa-back-btn', false);
});

it('affiche « Retour à la liste » sur Créer / Modifier / Voir une entreprise', function () {
    $company = Company::factory()->create();

    $this->get(CompanyResource::getUrl('create'))->assertOk()->assertSee('cfa-back-btn', false);
    $this->get(CompanyResource::getUrl('edit', ['record' => $company]))->assertOk()->assertSee('cfa-back-btn', false);
    $this->get(CompanyResource::getUrl('view', ['record' => $company]))->assertOk()->assertSee('cfa-back-btn', false);
});

it('ne montre PAS la flèche sur les listes ni le tableau de bord', function () {
    $this->get(CandidateResource::getUrl('index'))->assertOk()->assertDontSee('cfa-back-btn', false);
    $this->get(CompanyResource::getUrl('index'))->assertOk()->assertDontSee('cfa-back-btn', false);
    // Tableau de bord tenant-aware : sous le multi-tenant, « /admin » nu redirige
    // vers le tableau de bord du CFA courant (/admin/{slug}).
    $this->get(Dashboard::getUrl())->assertOk()->assertDontSee('cfa-back-btn', false);
});
