<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\Assiduite;
use App\Filament\Widgets\AssiduiteParPromotionChart;
use App\Models\Candidate;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Émarge l'unique apprenti d'une promo sur une séance donnée. */
function emarger(Seance $seance, Candidate $c, PresenceStatut $statut): void
{
    $seance->presences()->where('candidate_id', $c->id)->update(['statut' => $statut]);
}

it('calcule l\'assiduité d\'un apprenti (présents / renseignés)', function () {
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();

    $s1 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-01']);
    $s2 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-08']);

    emarger($s1, $c, PresenceStatut::Present);
    emarger($s2, $c, PresenceStatut::AbsentInjustifie);

    $a = $c->assiduite();

    expect($a['renseignees'])->toBe(2)
        ->and($a['presents'])->toBe(1)
        ->and($a['absences_injustifiees'])->toBe(1)
        ->and($a['taux'])->toBe(50);
});

it('restreint le calcul d\'assiduité à la période', function () {
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();

    $s1 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-01']);
    $s2 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-10-01']);
    emarger($s1, $c, PresenceStatut::Present);
    emarger($s2, $c, PresenceStatut::AbsentInjustifie);

    // Sur septembre seulement : 1 séance, présent → 100 %
    expect($c->assiduite('2026-09-01', '2026-09-30')['taux'])->toBe(100);
});

it('affiche la page assiduité aux rôles scolarité', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create(['nom' => 'Traore', 'prenom' => 'Salif']);

    Livewire::test(Assiduite::class)
        ->assertSuccessful()
        ->assertSee('Salif Traore');
});

/* ------------------------------------------------------------------
 |  Sélecteur de lecture du graphique (barres / courbe / camembert)
 * ------------------------------------------------------------------ */

/** Appelle une méthode protégée du widget (getData, getType…). */
function assiduiteWidget(string $vue): AssiduiteParPromotionChart
{
    $widget = new AssiduiteParPromotionChart;
    $widget->filter = $vue;

    return $widget;
}

function assiduiteAppel(AssiduiteParPromotionChart $widget, string $methode): mixed
{
    // PHP 8.1+ : les méthodes protégées sont invocables sans setAccessible().
    return (new ReflectionMethod($widget, $methode))->invoke($widget);
}

it('propose trois lectures et démarre sur les barres par promotion', function () {
    $widget = new AssiduiteParPromotionChart;

    expect(array_keys(assiduiteAppel($widget, 'getFilters')))
        ->toBe(['promotion', 'evolution', 'motifs'])
        ->and($widget->filter)->toBe('promotion')          // vue historique conservée
        ->and(assiduiteAppel($widget, 'getType'))->toBe('bar');
});

it('affiche l\'évolution mensuelle en courbe', function () {
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();
    $seance = Seance::factory()->create([
        'promotion_id' => $promo->id,
        'date' => now()->startOfMonth()->addDay()->toDateString(),
    ]);
    emarger($seance, $c, PresenceStatut::Present);

    $widget = assiduiteWidget('evolution');
    $data = assiduiteAppel($widget, 'getData');

    expect(assiduiteAppel($widget, 'getType'))->toBe('line')
        ->and($data['labels'])->toHaveCount(6)                       // 6 derniers mois
        ->and(end($data['datasets'][0]['data']))->toBe(100)          // mois courant : 100 %
        ->and($data['datasets'][0]['data'][0])->toBeNull();          // mois sans émargement → trou
});

it('affiche la répartition des motifs en camembert (parts d\'un tout)', function () {
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();

    $s1 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-01']);
    $s2 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-08']);
    $s3 = Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-15']);
    emarger($s1, $c, PresenceStatut::Present);
    emarger($s2, $c, PresenceStatut::AbsentInjustifie);
    emarger($s3, $c, PresenceStatut::Retard);

    $widget = assiduiteWidget('motifs');
    $data = assiduiteAppel($widget, 'getData');

    expect(assiduiteAppel($widget, 'getType'))->toBe('doughnut')
        ->and($data['labels'])->toBe(['Présents', 'Retards / départs anticipés', 'Absences injustifiées'])
        ->and($data['datasets'][0]['data'])->toBe([1, 1, 1])
        // « Absences justifiées » à zéro : écarté plutôt qu'affiché en part vide.
        ->and($data['labels'])->not->toContain('Absences justifiées');
});

it('ne rend cliquables que les barres par promotion', function () {
    expect(assiduiteAppel(assiduiteWidget('motifs'), 'getSegmentUrls'))->toBe([])
        ->and(assiduiteAppel(assiduiteWidget('evolution'), 'getSegmentUrls'))->toBe([]);
});

it('affiche le sélecteur des trois lectures dans le widget', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    Livewire::test(AssiduiteParPromotionChart::class)
        ->assertSuccessful()
        ->assertSee('Par promotion (barres)')
        ->assertSee('Évolution mensuelle (courbe)')
        ->assertSee('Répartition des motifs (camembert)')
        // Bascule de vue : le titre suit.
        ->set('filter', 'motifs')
        ->assertSee('Répartition des présences et absences');
});
