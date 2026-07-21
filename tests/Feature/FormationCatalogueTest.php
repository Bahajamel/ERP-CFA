<?php

use App\Filament\Resources\Formations\Pages\EditFormation;
use App\Filament\Resources\Formations\Pages\ViewFormation;
use App\Filament\Resources\Seances\Schemas\SeanceForm;
use App\Models\Formation;
use App\Models\Promotion;
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

it('affiche la fiche catalogue d\'une formation avec ses matières', function () {
    $formation = Formation::factory()->create([
        'libelle' => 'BTS SIO',
        'matieres' => ['Développement web', 'Bases de données', 'Cybersécurité'],
    ]);

    Livewire::test(ViewFormation::class, ['record' => $formation->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('BTS SIO')
        ->assertSee('Programme')
        ->assertSee('Développement web')
        ->assertSee('Bases de données')
        ->assertSee('Cybersécurité');
});

it('enregistre le programme (matières) depuis le formulaire', function () {
    $formation = Formation::factory()->create(['matieres' => null]);

    Livewire::test(EditFormation::class, ['record' => $formation->getRouteKey()])
        ->fillForm(['matieres' => ['Comptabilité', 'Fiscalité', 'Droit social']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($formation->refresh()->programme())->toBe(['Comptabilité', 'Fiscalité', 'Droit social']);
});

it('nettoie le programme : espaces superflus et doublons retirés', function () {
    $formation = Formation::factory()->create([
        'matieres' => ['  Maths  ', 'Maths', 'Anglais', ''],
    ]);

    expect($formation->programme())->toBe(['Maths', 'Anglais']);
});

it('propose les matières du catalogue à la création d\'une séance (synchronisation)', function () {
    $formation = Formation::factory()->create([
        'matieres' => ['Développement web', 'Cybersécurité', 'Anglais professionnel'],
    ]);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id]);

    // La séance d'une classe de cette formation se voit proposer son programme.
    expect(SeanceForm::matieresCatalogue($classe->id))
        ->toBe(['Développement web', 'Cybersécurité', 'Anglais professionnel']);

    // Ajouter une matière au catalogue la rend aussitôt disponible.
    $formation->update(['matieres' => [...$formation->matieres, 'Bases de données']]);

    expect(SeanceForm::matieresCatalogue($classe->id))
        ->toContain('Bases de données');
});
