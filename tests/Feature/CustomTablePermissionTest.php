<?php

use App\Models\CustomRecord;
use App\Models\CustomTable;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function ctpUtilisateur(string $role): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles($role);

    return $u;
}

it('accorde toutes les capacités (tables + lignes) à l\'Administrateur', function () {
    $admin = ctpUtilisateur('Administrateur');
    $this->actingAs($admin);

    $table = CustomTable::create(['name' => 'Partenaires']);
    $record = CustomRecord::create(['custom_table_id' => $table->id, 'data' => []]);

    expect($admin->can('viewAny', CustomTable::class))->toBeTrue()
        ->and($admin->can('create', CustomTable::class))->toBeTrue()
        ->and($admin->can('update', $table))->toBeTrue()
        ->and($admin->can('delete', $table))->toBeTrue()
        ->and($admin->can('forceDelete', $table))->toBeTrue()
        ->and($admin->can('create', CustomRecord::class))->toBeTrue()
        ->and($admin->can('update', $record))->toBeTrue()
        ->and($admin->can('delete', $record))->toBeTrue()
        ->and($admin->can('forceDelete', $record))->toBeTrue();
});

it('donne à la Direction tout sauf la suppression d\'une table', function () {
    $direction = ctpUtilisateur('Direction');
    $this->actingAs($direction);

    $table = CustomTable::create(['name' => 'Prospects']);

    expect($direction->can('viewAny', CustomTable::class))->toBeTrue()
        ->and($direction->can('create', CustomTable::class))->toBeTrue()
        ->and($direction->can('update', $table))->toBeTrue()
        ->and($direction->can('delete', $table))->toBeFalse()        // pas custom_tables.delete
        ->and($direction->can('forceDelete', $table))->toBeFalse()
        ->and($direction->can('create', CustomRecord::class))->toBeTrue()
        ->and($direction->can('delete', CustomRecord::query()->make(['custom_table_id' => $table->id])))->toBeTrue();
});

it('refuse tout accès aux tables personnalisées à un rôle non habilité', function () {
    $formateur = ctpUtilisateur('Formateur');

    expect($formateur->can('viewAny', CustomTable::class))->toBeFalse()
        ->and($formateur->can('create', CustomTable::class))->toBeFalse()
        ->and($formateur->can('viewAny', CustomRecord::class))->toBeFalse()
        ->and($formateur->can('create', CustomRecord::class))->toBeFalse();
});

it('réserve la suppression définitive à l\'Administrateur, même avec la permission delete', function () {
    $this->actingAs(ctpUtilisateur('Administrateur'));
    $table = CustomTable::create(['name' => 'X']);

    // Un utilisateur avec la permission delete mais SANS le rôle Administrateur.
    $chef = ctpUtilisateur('Direction');
    $chef->givePermissionTo('custom_tables.delete');

    expect($chef->can('delete', $table))->toBeTrue()          // archivage OK
        ->and($chef->can('forceDelete', $table))->toBeFalse(); // suppression définitive NON
});

it('génère un slug unique par CFA et trace l\'auteur à la création', function () {
    $admin = ctpUtilisateur('Administrateur');
    $this->actingAs($admin);

    $t1 = CustomTable::create(['name' => 'Suivi Ateliers']);
    $t2 = CustomTable::create(['name' => 'Suivi Ateliers']);

    expect($t1->slug)->toBe('suivi-ateliers')
        ->and($t2->slug)->toBe('suivi-ateliers-2')
        ->and($t1->created_by)->toBe($admin->id);
});

it('archive une table (soft delete) sans la détruire', function () {
    $this->actingAs(ctpUtilisateur('Administrateur'));
    $table = CustomTable::create(['name' => 'À archiver']);

    $table->delete();

    expect(CustomTable::query()->find($table->id))->toBeNull()
        ->and(CustomTable::withTrashed()->find($table->id))->not->toBeNull();
});

it('trace created_by et updated_by sur une ligne', function () {
    $auteur = ctpUtilisateur('Administrateur');
    $this->actingAs($auteur);

    $table = CustomTable::create(['name' => 'Suivi']);
    $record = CustomRecord::create(['custom_table_id' => $table->id, 'data' => ['a' => 1]]);

    expect($record->created_by)->toBe($auteur->id)
        ->and($record->updated_by)->toBe($auteur->id);

    $editeur = ctpUtilisateur('Direction');
    $this->actingAs($editeur);
    $record->update(['data' => ['a' => 2]]);

    expect($record->fresh()->updated_by)->toBe($editeur->id)   // dernière modif = éditeur
        ->and($record->fresh()->created_by)->toBe($auteur->id); // création inchangée
});
