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
        expect($kpi)->toHaveKeys(['cle', 'service', 'label', 'valeur', 'tone', 'icon', 'trend', 'spark', 'url'])
            ->and($kpi['spark'])->toHaveCount(6);
    }
});

it('reflète les entretiens à planifier dans la première carte KPI', function () {
    $this->seed(RolePermissionSeeder::class);
    Candidate::factory()->count(3)->create(['statut' => \App\Enums\CandidateStatut::EntretienAPlanifier]);

    $entretiens = collect(app(CockpitData::class)->kpis())->firstWhere('cle', 'entretiens');

    expect($entretiens['valeur'])->toBe('3');
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
        ->assertSee('Répartition des contrats')
        ->assertSeeHtml('wire:click="demanderIA"');
});

it('expose les onglets départements filtrés par les droits', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitUser());

    $tabs = collect(app(CockpitData::class)->departements());

    expect($tabs->pluck('label'))->toContain('Vue globale', 'Commercial', 'Admissions', 'Contrats', 'Finance', 'Pilotage')
        ->and($tabs->firstWhere('label', 'Vue globale')['actif'])->toBeTrue()
        // Chaque onglet porte son service (clé du filtre KPI).
        ->and($tabs->pluck('service'))->toContain('global', 'commercial', 'finance');
});

it('rattache chaque KPI à un service filtrable', function () {
    $this->seed(RolePermissionSeeder::class);

    $services = collect(app(CockpitData::class)->kpis())->pluck('service');

    expect($services)->toContain('commercial', 'admissions', 'contrats', 'finance', 'pilotage');
});

it('filtre les KPI par service via les onglets départements', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitUser());

    Livewire::test(CockpitWidget::class)
        ->assertSet('service', 'global')
        // Vue globale : tous les KPI, tous services confondus.
        ->assertSee('Entretiens à planifier')
        ->assertSee('Versements en retard')
        // Filtre « Finance » : ne reste que le KPI finance.
        ->call('definirService', 'finance')
        ->assertSet('service', 'finance')
        ->assertSee('Versements en retard')
        ->assertDontSee('Entretiens à planifier')
        // Valeur inconnue → repli sur « global ».
        ->call('definirService', 'nimportequoi')
        ->assertSet('service', 'global')
        ->assertSee('Entretiens à planifier');
});

it('change la fenêtre d\'historique via le filtre de période', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitUser());

    Livewire::test(CockpitWidget::class)
        ->assertSet('periode', '6')
        ->call('definirPeriode', '12')
        ->assertSet('periode', '12')
        ->assertOk();

    // 12 mois d'historique → 12 points sur les séries d'évolution.
    $evolution = app(CockpitData::class)->periode(12)->evolution();
    expect($evolution['labels'])->toHaveCount(12)
        ->and($evolution['series'][0]['data'])->toHaveCount(12);
});

it('produit un briefing IA sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(cockpitUser());

    Livewire::test(CockpitWidget::class)
        ->call('demanderIA')
        ->assertOk();
});
