<?php

use App\Enums\AdmissionStatut;
use App\Filament\Widgets\AdmissionStatsOverview;
use App\Filament\Widgets\DossiersAdmissionTable;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function connecteAvec(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);
    test()->actingAs($user);

    return $user;
}

it('réserve le dashboard admission aux rôles back-office', function () {
    foreach (['Admission', 'Administratif', 'Administrateur'] as $role) {
        connecteAvec($role);
        expect(AdmissionStatsOverview::canView())->toBeTrue()
            ->and(DossiersAdmissionTable::canView())->toBeTrue();
    }
});

it('cache le dashboard admission au commercial', function () {
    connecteAvec('Commercial');

    expect(AdmissionStatsOverview::canView())->toBeFalse()
        ->and(DossiersAdmissionTable::canView())->toBeFalse();
});

it('affiche les indicateurs des dossiers à traiter', function () {
    connecteAvec('Admission');

    Admission::factory()->create(['statut' => AdmissionStatut::Incomplet]);
    Admission::factory()->create(['statut' => AdmissionStatut::AVerifier]);
    Admission::factory()->create(['statut' => AdmissionStatut::Valide]); // ne compte pas

    Livewire::test(AdmissionStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Admissions à traiter')
        ->assertSee('Contrats à traiter')
        ->assertSee('Dossiers OPCO à traiter');
});

it('liste les admissions à finaliser dans le tableau', function () {
    connecteAvec('Admission');

    $candidate = Candidate::factory()->create(['nom' => 'Boubacar', 'prenom' => 'Aïcha']);
    Admission::factory()->create([
        'candidate_id' => $candidate->id,
        'statut' => AdmissionStatut::Incomplet,
    ]);

    Livewire::test(DossiersAdmissionTable::class)
        ->assertSuccessful()
        ->assertSee('Aïcha Boubacar');
});
