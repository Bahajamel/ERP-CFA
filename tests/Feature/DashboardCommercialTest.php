<?php

use App\Enums\CandidateStatut;
use App\Filament\Widgets\CommercialStatsOverview;
use App\Filament\Widgets\RelancesCommercialesTable;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Interaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function commercialConnecte(string $role = 'Commercial'): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);
    test()->actingAs($user);

    return $user;
}

it('réserve le dashboard commercial au Commercial et à l\'Administrateur', function () {
    foreach (['Commercial', 'Administrateur'] as $role) {
        commercialConnecte($role);

        expect(CommercialStatsOverview::canView())->toBeTrue()
            ->and(RelancesCommercialesTable::canView())->toBeTrue();
    }
});

it('cache le dashboard commercial aux autres rôles', function () {
    commercialConnecte('Direction');

    expect(CommercialStatsOverview::canView())->toBeFalse()
        ->and(RelancesCommercialesTable::canView())->toBeFalse();
});

it('affiche les indicateurs commerciaux du commercial connecté', function () {
    $user = commercialConnecte();

    Candidate::factory()->count(2)->create([
        'commercial_id' => $user->id,
        'statut' => CandidateStatut::Accepte,
    ]);
    // Candidat d'un autre commercial : ne doit pas gonfler « Mes candidats ».
    Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);

    Livewire::test(CommercialStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Candidats actifs')
        ->assertSee('Entretiens à mener')
        ->assertSee('Propositions envoyées')
        ->assertSee('Contrats signés')
        ->assertSee('À rappeler');
});

it('liste les relances échues du commercial dans son tableau', function () {
    $user = commercialConnecte();
    $company = Company::factory()->create(['raison_sociale' => 'Webtech Solutions']);

    Interaction::factory()->for($company, 'interactable')->avecRelance(now()->subDays(2)->toDateString())->create([
        'user_id' => $user->id,
        'prochaine_action' => 'Rappeler le tuteur',
    ]);

    Livewire::test(RelancesCommercialesTable::class)
        ->assertSuccessful()
        ->assertSee('Webtech Solutions')
        ->assertSee('Rappeler le tuteur');
});
