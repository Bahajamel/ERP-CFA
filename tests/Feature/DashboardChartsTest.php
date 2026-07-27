<?php

use App\Enums\ContractStatut;
use App\Enums\PresenceStatut;
use App\Filament\Pages\Assiduite;
use App\Filament\Resources\Seances\Pages\ListSeances;
use App\Filament\Widgets\AssiduiteParPromotionChart;
use App\Filament\Widgets\AssiduiteRepartitionChart;
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
        ->assertSee('statut', false)
        // Les options sont échappées pour l'attribut HTML : pas de JSON brut
        // (sinon les guillemets cassent x-data et le script fuit en texte).
        ->assertDontSee('{"plugins"', false);
});

it('rend la courbe des contrats signés et compte le mois courant', function () {
    Contract::factory()->create(['statut_contrat' => ContractStatut::Complet]);

    connecteAvecRole('Direction');

    Livewire::test(ContratsSignesParMoisChart::class)
        ->assertSuccessful()
        ->assertSee('onClick', false);
});

it('rend l\'assiduité par promotion, cliquable vers la page filtrée', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create();
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    connecteAvecRole('Scolarité');

    Livewire::test(AssiduiteParPromotionChart::class)
        ->assertSuccessful()
        ->assertSee('onClick', false)
        // Le clic mène à la liste des séances/émargement filtrée sur la classe.
        ->assertSee('seances', false)
        ->assertSee('promotion_id', false);
});

// ---------------------------------------------------------------------------
// Placement de chaque graphique dans sa section (widgets d'en-tête)
// ---------------------------------------------------------------------------

it('embarque le graphique d\'assiduité en tête de la page Assiduité', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create();
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    connecteAvecRole('Scolarité');

    Livewire::test(Assiduite::class)
        ->assertSuccessful()
        // La page montre UNE classe à la fois (Formation → Classe), et non plus
        // l'agrégat de toutes les formations : c'est le widget « Répartition ».
        ->assertSeeLivewire(AssiduiteRepartitionChart::class)
        ->assertDontSeeLivewire(AssiduiteParPromotionChart::class);
});

it('le clic mène à la liste des séances filtrée sur la classe (deep-link)', function () {
    $promoA = Promotion::factory()->create();
    $promoB = Promotion::factory()->create();
    $seanceA = Seance::factory()->create(['promotion_id' => $promoA->id]);
    $seanceB = Seance::factory()->create(['promotion_id' => $promoB->id]);

    connecteAvecRole('Scolarité');

    Livewire::withQueryParams(['filters' => ['promotion_id' => ['value' => (string) $promoA->id]]])
        ->test(ListSeances::class)
        ->assertCanSeeTableRecords([$seanceA])
        ->assertCanNotSeeTableRecords([$seanceB]);
});

it('calcule le taux de présence par promotion', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create();
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    connecteAvecRole('Direction');

    $widget = new AssiduiteParPromotionChart;
    $data = Closure::bind(fn () => $this->getData(), $widget, $widget)();

    expect($data['labels'])->toContain($promo->libelle)
        ->and($data['datasets'][0]['data'])->toContain(100);
});
