<?php

use App\Filament\Widgets\GuideDemarrageWidget;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function guideUser(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);

    return $user;
}

it('affiche le cycle de vie et le bouton d\'ajout à un profil habilité', function () {
    $this->actingAs(guideUser('Administrateur'));

    Livewire::test(GuideDemarrageWidget::class)
        ->assertSuccessful()
        ->assertSee('le cycle de vie')
        ->assertSee('Candidat')
        ->assertSee('Dossier OPCO')
        ->assertSee('Ajouter un apprenant');
});

it('propose les 7 étapes avec des liens quand l\'accès est complet', function () {
    $this->actingAs(guideUser('Administrateur'));

    $steps = (new GuideDemarrageWidget)->steps();

    expect($steps)->toHaveCount(7)
        ->and(collect($steps)->every(fn ($s) => $s['url'] !== null))->toBeTrue();
});

it('masque les liens des modules non autorisés', function () {
    // Le formateur n'a pas accès aux candidats.
    $this->actingAs(guideUser('Formateur'));

    $widget = new GuideDemarrageWidget;
    $candidatStep = collect($widget->steps())->firstWhere('title', 'Candidat');

    expect($candidatStep['url'])->toBeNull()
        ->and($widget->nouvelApprenantUrl())->toBeNull();
});
