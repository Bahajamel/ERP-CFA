<?php

use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('affiche la page « Modifier » premium (carte résumé + progression)', function () {
    $candidate = Candidate::factory()->create(['nom' => 'Benali', 'prenom' => 'Karim']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Modifier les informations')
        ->assertSee('Progression du dossier')
        ->assertSee('Karim Benali');
});

it('enregistre les modifications via le formulaire embarqué (save intact)', function () {
    $candidate = Candidate::factory()->create(['nom' => 'Ancien', 'prenom' => 'Nom']);

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'nom' => 'Nouveau',
            'prenom' => 'Prénom',
            'email' => 'nouveau.prenom@exemple.fr',
            'telephone' => '+33612345678',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($candidate->fresh()->nom)->toBe('Nouveau')
        ->and($candidate->fresh()->prenom)->toBe('Prénom');
});

it('conserve la validation existante du formulaire (email ou téléphone requis)', function () {
    $candidate = Candidate::factory()->create();

    Livewire::test(EditCandidate::class, ['record' => $candidate->getRouteKey()])
        ->fillForm([
            'email' => null,
            'telephone' => null,
        ])
        ->call('save')
        ->assertHasFormErrors(['email', 'telephone']);
});
