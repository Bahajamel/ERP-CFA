<?php

use App\Enums\ContractStatut;
use App\Filament\Pages\CockpitDirection;
use App\Filament\Widgets\ContratsSignesParMoisChart;
use App\Models\Contract;
use App\Models\User;
use App\Services\RapportPilotageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function directionConnecte(string $role = 'Direction'): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);
    test()->actingAs($user);

    return $user;
}

// ---------------------------------------------------------------------------
// Courbe contrats : comparaison N-1
// ---------------------------------------------------------------------------

it('affiche 2 courbes en mode comparaison N-1, 1 seule sinon', function () {
    $chart = new ContratsSignesParMoisChart();

    $chart->filter = 'comparer';
    $avecCompare = Closure::bind(fn () => $this->getData(), $chart, $chart)();
    expect($avecCompare['datasets'])->toHaveCount(2);

    $chart->filter = 'simple';
    $sansCompare = Closure::bind(fn () => $this->getData(), $chart, $chart)();
    expect($sansCompare['datasets'])->toHaveCount(1);
});

it('propose les filtres de période sur la courbe', function () {
    $chart = new ContratsSignesParMoisChart();
    $filtres = Closure::bind(fn () => $this->getFilters(), $chart, $chart)();

    expect($filtres)->toHaveKeys(['simple', 'comparer']);
});

// ---------------------------------------------------------------------------
// Rapport de pilotage
// ---------------------------------------------------------------------------

it('agrège les indicateurs clés du rapport de pilotage', function () {
    Contract::factory()->count(2)->create(['statut_contrat' => ContractStatut::Actif]);

    $donnees = app(RapportPilotageService::class)->donnees();

    expect($donnees)->toHaveKeys(['candidats', 'admissions', 'contrats', 'opco', 'finance', 'assiduite', 'alertes'])
        ->and($donnees['contrats']['signes'])->toBeGreaterThanOrEqual(2)
        ->and($donnees['finance'])->toHaveKeys(['facture', 'encaisse', 'taux', 'impayes']);
});

// ---------------------------------------------------------------------------
// Cockpit Direction
// ---------------------------------------------------------------------------

it('réserve le cockpit à la Direction et à l\'Administrateur', function () {
    $this->seed(RolePermissionSeeder::class);

    directionConnecte('Direction');
    expect(CockpitDirection::canAccess())->toBeTrue();

    directionConnecte('Commercial');
    expect(CockpitDirection::canAccess())->toBeFalse();
});

it('rend le cockpit et permet de télécharger le rapport PDF', function () {
    $this->seed(RolePermissionSeeder::class);
    directionConnecte('Direction');

    Livewire::test(CockpitDirection::class)
        ->assertSuccessful()
        ->callAction('rapportPdf')
        ->assertSuccessful();
});
