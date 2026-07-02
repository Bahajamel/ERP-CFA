<?php

use App\Enums\ContractStatut;
use App\Enums\PresenceStatut;
use App\Filament\Pages\Assiduite;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Widgets\AssiduiteParPromotionChart;
use App\Filament\Widgets\ContratsSignesParMoisChart;
use App\Filament\Widgets\ConversionFunnelChart;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function connecteAvecRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);
    test()->actingAs($user);

    return $user;
}

// ---------------------------------------------------------------------------
// Gating par rôle
// ---------------------------------------------------------------------------

it('réserve le graphique des contrats signés à la Direction', function () {
    connecteAvecRole('Direction');
    expect(ContratsSignesParMoisChart::canView())->toBeTrue();

    connecteAvecRole('Commercial');
    expect(ContratsSignesParMoisChart::canView())->toBeFalse();
});

it('ouvre le graphique d\'assiduité aux profils ayant accès à l\'assiduité', function () {
    connecteAvecRole('Scolarité');
    expect(AssiduiteParPromotionChart::canView())->toBeTrue();

    connecteAvecRole('Commercial');
    expect(AssiduiteParPromotionChart::canView())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Rendu + cliquabilité (P0-12-4)
// ---------------------------------------------------------------------------

it('rend l\'entonnoir avec des segments cliquables vers les listes filtrées', function () {
    connecteAvecRole('Direction');

    Livewire::test(ConversionFunnelChart::class)
        ->assertSuccessful()
        ->assertSee('onClick', false)
        ->assertSee('tableFilters', false)
        // Les options sont échappées pour l'attribut HTML : pas de JSON brut
        // (sinon les guillemets cassent x-data et le script fuit en texte).
        ->assertDontSee('{"plugins"', false);
});

it('rend la courbe des contrats signés et compte le mois courant', function () {
    Contract::factory()->create(['statut_contrat' => ContractStatut::Signe]);

    connecteAvecRole('Direction');

    Livewire::test(ContratsSignesParMoisChart::class)
        ->assertSuccessful()
        ->assertSee('onClick', false);
});

it('rend l\'assiduité par promotion, cliquable vers la page filtrée', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->create(['promotion_id' => $promo->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    connecteAvecRole('Scolarité');

    Livewire::test(AssiduiteParPromotionChart::class)
        ->assertSuccessful()
        ->assertSee('onClick', false)
        ->assertSee('assiduite?classe=', false);
});

// ---------------------------------------------------------------------------
// Placement de chaque graphique dans sa section (widgets d'en-tête)
// ---------------------------------------------------------------------------

it('embarque la courbe des contrats en tête de la liste des contrats', function () {
    connecteAvecRole('Direction');

    Livewire::test(ListContracts::class)
        ->assertSuccessful()
        ->assertSeeLivewire(ContratsSignesParMoisChart::class);
});

it('embarque l\'entonnoir en tête de la liste des admissions', function () {
    connecteAvecRole('Direction');

    Livewire::test(ListAdmissions::class)
        ->assertSuccessful()
        ->assertSeeLivewire(ConversionFunnelChart::class);
});

it('embarque le graphique d\'assiduité en tête de la page Assiduité', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->create(['promotion_id' => $promo->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    connecteAvecRole('Scolarité');

    Livewire::test(Assiduite::class)
        ->assertSuccessful()
        ->assertSeeLivewire(AssiduiteParPromotionChart::class);
});

it('la page Assiduité filtre sur la classe depuis ?classe=<id> (deep-link du clic)', function () {
    $promoA = Promotion::factory()->create();
    $promoB = Promotion::factory()->create();
    $alpha = Candidate::factory()->create(['promotion_id' => $promoA->id]);
    $beta = Candidate::factory()->create(['promotion_id' => $promoB->id]);

    connecteAvecRole('Scolarité');

    Livewire::test(Assiduite::class, ['classe' => (string) $promoA->id])
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

it('calcule le taux de présence par promotion', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->create(['promotion_id' => $promo->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    connecteAvecRole('Direction');

    $widget = new AssiduiteParPromotionChart();
    $data = Closure::bind(fn () => $this->getData(), $widget, $widget)();

    expect($data['labels'])->toContain($promo->libelle)
        ->and($data['datasets'][0]['data'])->toContain(100);
});
