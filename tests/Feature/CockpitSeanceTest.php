<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\CockpitSeance;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
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
    $user->syncRoles('Administrateur');
    $this->actingAs($user);
});

/** Séance avec n apprentis rattachés (leurs présences sont générées à la création). */
function seancePourCockpit(int $n = 2): Seance
{
    $formation = Formation::factory()->create();
    $promotion = Promotion::factory()->create(['formation_id' => $formation->id]);
    $apprentis = Candidate::factory()->count($n)->create();
    $promotion->apprentis()->attach($apprentis->pluck('id'));

    return Seance::factory()->create(['promotion_id' => $promotion->id]);
}

it('affiche le cockpit avec les apprenants et les compteurs', function () {
    $seance = seancePourCockpit(2);
    $apprenti = $seance->presences()->with('candidate')->first()->candidate;

    Livewire::test(CockpitSeance::class, ['seance' => $seance->id])
        ->assertOk()
        ->assertSee($apprenti->nom_complet)
        ->assertSee('Signatures');
});

it('change le statut d\'une présence en un clic', function () {
    $seance = seancePourCockpit(2);
    $presence = $seance->presences()->first();

    Livewire::test(CockpitSeance::class, ['seance' => $seance->id])
        ->call('definirStatut', $presence->id, PresenceStatut::Present->value)
        ->assertHasNoErrors();

    expect($presence->fresh()->statut)->toBe(PresenceStatut::Present);
});

it('ignore un statut invalide', function () {
    $seance = seancePourCockpit(1);
    $presence = $seance->presences()->first();

    Livewire::test(CockpitSeance::class, ['seance' => $seance->id])
        ->call('definirStatut', $presence->id, 'statut_bidon');

    expect($presence->fresh()->statut)->toBe(PresenceStatut::NonRenseigne);
});

it('renvoie 404 pour une séance inexistante', function () {
    Livewire::test(CockpitSeance::class, ['seance' => 999999])
        ->assertNotFound();
});
