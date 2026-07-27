<?php

use App\Models\Candidate;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function utilisateur(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles($role);

    return $user;
}

it('autorise les capacités quand l\'utilisateur a la permission du module', function () {
    // Commercial dispose de access_candidates.
    $commercial = utilisateur('Commercial');
    $candidat = Candidate::factory()->create();

    expect($commercial->can('viewAny', Candidate::class))->toBeTrue()
        ->and($commercial->can('view', $candidat))->toBeTrue()
        ->and($commercial->can('create', Candidate::class))->toBeTrue()
        ->and($commercial->can('update', $candidat))->toBeTrue()
        ->and($commercial->can('delete', $candidat))->toBeTrue();
});

it('refuse les capacités quand l\'utilisateur n\'a pas la permission du module', function () {
    // Commercial n'a PAS access_contracts.
    $commercial = utilisateur('Commercial');
    $contrat = Contract::factory()->create();

    expect($commercial->can('viewAny', Contract::class))->toBeFalse()
        ->and($commercial->can('view', $contrat))->toBeFalse()
        ->and($commercial->can('update', $contrat))->toBeFalse()
        ->and($commercial->can('delete', $contrat))->toBeFalse();
});

it('donne à l\'administrateur toutes les capacités (toutes permissions)', function () {
    $admin = utilisateur('Administrateur');
    $candidat = Candidate::factory()->create();
    $contrat = Contract::factory()->create();

    expect($admin->can('view', $candidat))->toBeTrue()
        ->and($admin->can('delete', $candidat))->toBeTrue()
        ->and($admin->can('update', $contrat))->toBeTrue()
        ->and($admin->can('forceDelete', $contrat))->toBeTrue();
});

it('cloisonne par module : Finance voit la finance, pas les candidats', function () {
    $finance = utilisateur('Finance');
    $candidat = Candidate::factory()->create();

    expect($finance->can('view', $candidat))->toBeFalse()
        ->and($finance->can('viewAny', Candidate::class))->toBeFalse();
});
