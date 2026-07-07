<?php

use App\Filament\Pages\EmploiDuTemps;
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
    $user->syncRoles('Formateur');
    $this->actingAs($user);
});

it('affiche les séances de la semaine pour la formation choisie', function () {
    $formation = Formation::factory()->create(['libelle' => 'CDA Test']);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'libelle' => '1ère année', 'matiere' => 'Développement web']);
    Seance::factory()->create([
        'promotion_id' => $classe->id,
        'date' => now()->startOfWeek()->addDay()->toDateString(), // mardi de la semaine courante
        'heure_debut' => '09:00',
        'heure_fin' => '12:00',
    ]);

    Livewire::test(EmploiDuTemps::class, ['formationId' => $formation->id])
        ->assertSuccessful()
        ->assertSee('Développement web')
        ->assertSee('09:00');
});

it('n\'affiche pas les séances des autres formations', function () {
    $formation = Formation::factory()->create();
    $autre = Formation::factory()->create();
    $classeAutre = Promotion::factory()->create(['formation_id' => $autre->id, 'matiere' => 'MatiereInvisible']);
    Seance::factory()->create([
        'promotion_id' => $classeAutre->id,
        'date' => now()->startOfWeek()->toDateString(),
    ]);

    Livewire::test(EmploiDuTemps::class, ['formationId' => $formation->id])
        ->assertSuccessful()
        ->assertDontSee('MatiereInvisible');
});

it('navigue de semaine en semaine', function () {
    $formation = Formation::factory()->create();
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'matiere' => 'SemaineProchaine']);
    Seance::factory()->create([
        'promotion_id' => $classe->id,
        'date' => now()->startOfWeek()->addWeek()->toDateString(), // lundi prochain
    ]);

    Livewire::test(EmploiDuTemps::class, ['formationId' => $formation->id])
        ->assertDontSee('SemaineProchaine')
        ->call('semaineSuivante')
        ->assertSee('SemaineProchaine')
        ->call('semaineCourante')
        ->assertDontSee('SemaineProchaine');
});
