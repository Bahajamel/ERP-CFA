<?php

use App\Filament\Widgets\ConversionFunnelChart;
use App\Filament\Widgets\DirectionStatsOverview;
use App\Filament\Widgets\FinancementChart;
use App\Filament\Widgets\OpcoBloquesTable;
use App\Filament\Widgets\TachesPrioritairesTable;
use App\Models\Candidate;
use App\Models\OpcoFile;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function userAvecRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);

    return $user;
}

$widgets = [
    DirectionStatsOverview::class,
    ConversionFunnelChart::class,
    FinancementChart::class,
    OpcoBloquesTable::class,
    TachesPrioritairesTable::class,
];

it('réserve les widgets de pilotage à la Direction et à l\'Administrateur', function (string $widget) {
    $this->actingAs(userAvecRole('Direction'));
    expect($widget::canView())->toBeTrue();

    $this->actingAs(userAvecRole('Administrateur'));
    expect($widget::canView())->toBeTrue();
})->with($widgets);

it('cache les widgets de pilotage aux autres rôles', function (string $widget) {
    $this->actingAs(userAvecRole('Commercial'));
    expect($widget::canView())->toBeFalse();

    $this->actingAs(userAvecRole('Formateur'));
    expect($widget::canView())->toBeFalse();
})->with($widgets);

it('affiche les indicateurs clés à la direction', function () {
    Candidate::factory()->count(3)->create();
    Task::factory()->count(2)->create();

    $this->actingAs(userAvecRole('Direction'));

    Livewire::test(DirectionStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Candidats actifs')
        ->assertSee('Financement OPCO encaissé');
});

it('affiche l\'entonnoir de conversion et la trésorerie sans erreur', function () {
    OpcoFile::factory()->count(2)->create();

    $this->actingAs(userAvecRole('Direction'));

    Livewire::test(ConversionFunnelChart::class)->assertSuccessful();
    Livewire::test(FinancementChart::class)->assertSuccessful();
});
