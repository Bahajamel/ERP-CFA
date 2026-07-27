<?php

use App\Enums\TaskStatut;
use App\Filament\Widgets\MesActionsStats;
use App\Filament\Widgets\MesTachesTable;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('est visible pour tout utilisateur connecté', function () {
    $this->actingAs(User::factory()->create(['is_active' => true]));

    expect(MesActionsStats::canView())->toBeTrue()
        ->and(MesTachesTable::canView())->toBeTrue();
});

it('affiche mes indicateurs personnels sans erreur', function () {
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user);

    Task::factory()->count(2)->create(['assignee_id' => $user->id, 'statut' => TaskStatut::AFaire]);

    Livewire::test(MesActionsStats::class)
        ->assertSuccessful()
        ->assertSee('À faire')
        ->assertSee('Notifications');
});

it('ne liste que mes tâches et permet de les terminer', function () {
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user);

    $mienne = Task::factory()->create([
        'assignee_id' => $user->id,
        'statut' => TaskStatut::AFaire,
    ]);
    $autre = Task::factory()->create([
        'assignee_id' => User::factory()->create()->id,
        'statut' => TaskStatut::AFaire,
    ]);

    Livewire::test(MesTachesTable::class)
        ->assertCanSeeTableRecords([$mienne])
        ->assertCanNotSeeTableRecords([$autre])
        ->callTableAction('terminer', $mienne);

    expect($mienne->refresh()->statut)->toBe(TaskStatut::Terminee);
});
