<?php

use App\Enums\ContractStatut;
use App\Filament\Widgets\CockpitWidget;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\User;
use App\Services\CockpitData;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function cockpitUser(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

it('renvoie les six cartes KPI avec une mini-courbe à six points', function () {
    $this->seed(RolePermissionSeeder::class);
    Candidate::factory()->count(4)->create();
    Contract::factory()->create(['statut_contrat' => ContractStatut::Complet]);

    $kpis = app(CockpitData::class)->kpis();

    expect($kpis)->toHaveCount(6);
    foreach ($kpis as $kpi) {
        expect($kpi)->toHaveKeys(['cle', 'label', 'valeur', 'tone', 'icon', 'trend', 'spark', 'url'])
            ->and($kpi['spark'])->toHaveCount(6);
    }
});

it('reflète les candidats actifs dans la première carte KPI', function () {
    $this->seed(RolePermissionSeeder::class);
    Candidate::factory()->count(3)->create();

    $candidats = collect(app(CockpitData::class)->kpis())->firstWhere('cle', 'candidats');

    expect($candidats['valeur'])->toBe('3');
});

it('construit un entonnoir décroissant candidats → contrats', function () {
    $this->seed(RolePermissionSeeder::class);
    Candidate::factory()->count(5)->create(['statut' => \App\Enums\CandidateStatut::EntretienAPlanifier]);

    $pipeline = app(CockpitData::class)->pipeline();

    expect($pipeline['etapes'])->toHaveCount(4)
        ->and($pipeline['etapes'][0]['label'])->toBe('Candidats')
        ->and($pipeline['etapes'][0]['valeur'])->toBe(5)
        ->and($pipeline)->toHaveKey('global');
});

it('renvoie une répartition des contrats dont le total est cohérent', function () {
    $this->seed(RolePermissionSeeder::class);
    Contract::factory()->create(['statut_contrat' => ContractStatut::EnCours]);

    $dist = app(CockpitData::class)->contractDistribution();

    expect($dist['segments'])->toHaveCount(4)
        ->and($dist['total'])->toBe(array_sum(array_column($dist['segments'], 'valeur')));
});

it('fournit un résumé intelligent avec les quatre volets', function () {
    $this->seed(RolePermissionSeeder::class);

    $insights = app(CockpitData::class)->insights();

    expect($insights)->toHaveKeys(['a_retenir', 'anomalies', 'recommandations', 'actions'])
        ->and($insights['anomalies'])->not->toBeEmpty()
        ->and($insights['recommandations'])->not->toBeEmpty();
});

it('rend le widget cockpit sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitUser());

    Livewire::test(CockpitWidget::class)
        ->assertOk()
        ->assertSee('Vue globale')
        ->assertSee('Supervision intelligente')
        ->assertSee('Pipeline commercial')
        ->assertSee('Répartition des contrats');
});
