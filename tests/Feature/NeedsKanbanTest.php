<?php

use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\Pages\NeedsKanban;
use App\Models\Need;
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
    $user->syncRoles('Commercial'); // dispose de access_needs
    $this->actingAs($user);
});

it('groupe les besoins par statut dans les colonnes de l\'entonnoir', function () {
    Need::factory()->create(['statut' => NeedStatut::Cree]);
    Need::factory()->count(2)->create(['statut' => NeedStatut::ProfilsEnvoyes]);

    $columns = Livewire::test(NeedsKanban::class)->instance()->getColumns();
    $parStatut = collect($columns)->keyBy(fn (array $c): string => $c['statut']->value);

    expect($parStatut[NeedStatut::Cree->value]['needs'])->toHaveCount(1)
        ->and($parStatut[NeedStatut::ProfilsEnvoyes->value]['needs'])->toHaveCount(2);
});

it('déplace un besoin vers un statut autorisé (transition appliquée)', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Cree]);

    Livewire::test(NeedsKanban::class)
        ->call('moveCard', $need->id, NeedStatut::EnQualification->value)
        ->assertNotified();

    expect($need->fresh()->statut)->toBe(NeedStatut::EnQualification);
});

it('refuse un déplacement interdit et conserve le statut', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Cree]);

    Livewire::test(NeedsKanban::class)
        ->call('moveCard', $need->id, NeedStatut::Pourvu->value)
        ->assertNotified();

    expect($need->fresh()->statut)->toBe(NeedStatut::Cree);
});

it('ignore un dépôt dans la même colonne (aucune transition tentée)', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Cree]);

    Livewire::test(NeedsKanban::class)
        ->call('moveCard', $need->id, NeedStatut::Cree->value);

    expect($need->fresh()->statut)->toBe(NeedStatut::Cree);
});
