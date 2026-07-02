<?php

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function utilisateurAvecPermissions(array $slugs): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->givePermissionTo(array_map(fn (string $s): string => "access_{$s}", $slugs));
    test()->actingAs($user);

    return $user;
}

it('réserve l\'export aux utilisateurs autorisés (droit reports)', function () {
    // Accès aux candidats + droit d'export → action visible.
    utilisateurAvecPermissions(['candidates', 'reports']);
    Livewire::test(ListCandidates::class)->assertActionVisible('export');

    // Accès aux candidats sans droit d'export → action cachée.
    utilisateurAvecPermissions(['candidates']);
    Livewire::test(ListCandidates::class)->assertActionHidden('export');
});

it('conserve l\'auteur et la date de génération de l\'export (P1-19-2)', function () {
    $user = utilisateurAvecPermissions(['candidates', 'reports']);
    Candidate::factory()->count(3)->create();

    Livewire::test(ListCandidates::class)
        ->callAction('export');

    $export = Export::query()->latest('id')->first();

    expect($export)->not->toBeNull()
        ->and($export->user_id)->toBe($user->id)
        ->and($export->created_at)->not->toBeNull();
});
