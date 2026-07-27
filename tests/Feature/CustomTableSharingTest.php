<?php

use App\Filament\Resources\CustomTables\Pages\BoardCustomTable;
use App\Models\CustomTable;
use App\Models\CustomTableShare;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Créateur du tableau (Commercial).
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->syncRoles('Commercial');
    $this->actingAs($this->owner);
});

/** Crée un tableau appartenant au créateur courant. */
function tableauPrive(): CustomTable
{
    return CustomTable::create(['name' => 'Suivi partenariats', 'context' => 'candidate']);
}

it('un tableau est privé à son créateur par défaut', function () {
    $table = tableauPrive();

    $autre = User::factory()->create(['is_active' => true]);
    $autre->syncRoles('Commercial');

    expect($table->accessiblePar($this->owner))->toBeTrue()
        ->and($table->modifiablePar($this->owner))->toBeTrue()
        ->and($table->accessiblePar($autre))->toBeFalse()
        ->and($table->modifiablePar($autre))->toBeFalse();
});

it('la supervision (Administrateur / Direction) voit tous les tableaux', function () {
    $table = tableauPrive();

    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles('Administrateur');
    $direction = User::factory()->create(['is_active' => true]);
    $direction->syncRoles('Direction');

    expect($table->accessiblePar($admin))->toBeTrue()
        ->and($table->modifiablePar($admin))->toBeTrue()
        ->and($table->accessiblePar($direction))->toBeTrue()
        ->and($table->modifiablePar($direction))->toBeTrue();
});

it('une invitation « lecture » donne la vue mais pas la modification', function () {
    $table = tableauPrive();
    $invite = User::factory()->create(['is_active' => true]);
    $invite->syncRoles('Commercial');

    CustomTableShare::create([
        'custom_table_id' => $table->id,
        'user_id' => $invite->id,
        'role' => CustomTableShare::ROLE_LECTURE,
    ]);

    expect($table->accessiblePar($invite))->toBeTrue()
        ->and($table->modifiablePar($invite))->toBeFalse();
});

it('une invitation « modification » donne la vue ET la modification', function () {
    $table = tableauPrive();
    $invite = User::factory()->create(['is_active' => true]);
    $invite->syncRoles('Commercial');

    CustomTableShare::create([
        'custom_table_id' => $table->id,
        'user_id' => $invite->id,
        'role' => CustomTableShare::ROLE_MODIFICATION,
    ]);

    expect($table->accessiblePar($invite))->toBeTrue()
        ->and($table->modifiablePar($invite))->toBeTrue();
});

it('le scope accessiblePar filtre la liste selon l\'utilisateur', function () {
    $table = tableauPrive();
    $invite = User::factory()->create(['is_active' => true]);
    $invite->syncRoles('Commercial');
    $etranger = User::factory()->create(['is_active' => true]);
    $etranger->syncRoles('Commercial');

    CustomTableShare::create(['custom_table_id' => $table->id, 'user_id' => $invite->id, 'role' => 'lecture']);

    expect(CustomTable::query()->accessiblePar($this->owner)->pluck('id')->all())->toContain($table->id)
        ->and(CustomTable::query()->accessiblePar($invite)->pluck('id')->all())->toContain($table->id)
        ->and(CustomTable::query()->accessiblePar($etranger)->pluck('id')->all())->not->toContain($table->id);
});

it('la policy applique le privé (view/update) selon le partage', function () {
    $table = tableauPrive();
    $etranger = User::factory()->create(['is_active' => true]);
    $etranger->syncRoles('Commercial');

    expect($etranger->can('view', $table))->toBeFalse()
        ->and($etranger->can('update', $table))->toBeFalse()
        ->and($this->owner->can('view', $table))->toBeTrue()
        ->and($this->owner->can('update', $table))->toBeTrue();
});

it('le créateur invite un membre via l\'action Partager (accès + notification)', function () {
    $table = tableauPrive();
    $invite = User::factory()->create(['is_active' => true]);
    $invite->syncRoles('Commercial');

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->callAction('partager', data: [
            'partages' => [
                ['user_id' => $invite->id, 'role' => CustomTableShare::ROLE_MODIFICATION],
            ],
        ]);

    $table->refresh();

    expect($table->partages()->where('user_id', $invite->id)->value('role'))->toBe('modification')
        ->and($table->modifiablePar($invite))->toBeTrue()
        ->and($invite->fresh()->notifications()->count())->toBe(1); // invité prévenu
});

it('révoque un accès en le retirant de la liste', function () {
    $table = tableauPrive();
    $invite = User::factory()->create(['is_active' => true]);
    $invite->syncRoles('Commercial');
    CustomTableShare::create(['custom_table_id' => $table->id, 'user_id' => $invite->id, 'role' => 'lecture']);

    // Envoi d'une liste vide → l'accès est retiré.
    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->callAction('partager', data: ['partages' => []]);

    expect($table->fresh()->partages()->count())->toBe(0)
        ->and($table->accessiblePar($invite))->toBeFalse();
});

it('un membre sans accès ne peut pas partager (action réservée au gestionnaire)', function () {
    $table = tableauPrive();
    $etranger = User::factory()->create(['is_active' => true]);
    $etranger->syncRoles('Commercial');

    expect($etranger->can('share', $table))->toBeFalse()
        ->and($this->owner->can('share', $table))->toBeTrue();
});
