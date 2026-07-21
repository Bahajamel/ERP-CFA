<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\Assiduite;
use App\Filament\Widgets\AssiduiteRepartitionChart;
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
 |  Graphique : choix de la classe (filtre) et de la forme (barres /
 |  camembert / anneau).
 * ------------------------------------------------------------------ */

/** Widget configuré sur une forme et, éventuellement, une classe. */
function assiduiteWidget(string $forme = 'bar', ?int $classeId = null): AssiduiteRepartitionChart
{
    $widget = new AssiduiteRepartitionChart;
    $widget->filter = $forme;
    $widget->filters = ['classe' => $classeId];

    return $widget;
}

function assiduiteAppel(AssiduiteRepartitionChart $widget, string $methode): mixed
{
    // PHP 8.1+ : les méthodes protégées sont invocables sans setAccessible().
    return (new ReflectionMethod($widget, $methode))->invoke($widget);
}

/** Une classe avec 1 présent, 1 retard, 1 absence injustifiée. */
function assiduiteJeuDEssai(): Promotion
{
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->dansClasse($promo)->create();

    foreach ([
        ['2026-09-01', PresenceStatut::Present],
        ['2026-09-08', PresenceStatut::Retard],
        ['2026-09-15', PresenceStatut::AbsentInjustifie],
    ] as [$date, $statut]) {
        emarger(Seance::factory()->create(['promotion_id' => $promo->id, 'date' => $date]), $c, $statut);
    }

    return $promo;
}

it('propose les trois formes et démarre sur les barres', function () {
    $widget = new AssiduiteRepartitionChart;

    expect(assiduiteAppel($widget, 'getFilters'))
        ->toBe(['bar' => 'Barres', 'pie' => 'Camembert', 'doughnut' => 'Anneau'])
        ->and($widget->filter)->toBe('bar')
        ->and(assiduiteAppel($widget, 'getType'))->toBe('bar');
});

it('rend la même répartition dans les trois formes', function () {
    assiduiteJeuDEssai();

    foreach (['bar', 'pie', 'doughnut'] as $forme) {
        $widget = assiduiteWidget($forme);

        expect(assiduiteAppel($widget, 'getType'))->toBe($forme)
            ->and(assiduiteAppel($widget, 'getData')['datasets'][0]['data'])->toBe([1, 1, 1]);
    }
});

it('écarte les motifs à zéro de la répartition', function () {
    assiduiteJeuDEssai(); // aucune absence justifiée

    $data = assiduiteAppel(assiduiteWidget('pie'), 'getData');

    expect($data['labels'])->toBe(['Présents', 'Retards / départs anticipés', 'Absences injustifiées'])
        ->and($data['labels'])->not->toContain('Absences justifiées');
});

it('restreint les statistiques à la classe choisie', function () {
    $classeA = assiduiteJeuDEssai();

    // Une seconde classe, entièrement présente : elle ne doit pas polluer A.
    $classeB = Promotion::factory()->create();
    $cB = Candidate::factory()->dansClasse($classeB)->create();
    emarger(Seance::factory()->create(['promotion_id' => $classeB->id, 'date' => '2026-09-01']), $cB, PresenceStatut::Present);

    $global = assiduiteAppel(assiduiteWidget('bar'), 'getData');
    $surA = assiduiteAppel(assiduiteWidget('bar', $classeA->id), 'getData');
    $surB = assiduiteAppel(assiduiteWidget('bar', $classeB->id), 'getData');

    expect(array_sum($global['datasets'][0]['data']))->toBe(4)   // 3 + 1
        ->and(array_sum($surA['datasets'][0]['data']))->toBe(3)
        ->and($surB['labels'])->toBe(['Présents'])               // classe B : que des présents
        ->and($surB['datasets'][0]['data'])->toBe([1]);
});

it('affiche le taux de présence du périmètre en description', function () {
    $classe = assiduiteJeuDEssai(); // présent + retard comptent présents → 2/3

    expect(assiduiteWidget('bar', $classe->id)->getDescription())->toContain('67 %')
        ->and(assiduiteWidget('bar')->getHeading())->toBe('Répartition — toutes les classes');
});

it('ne rend les segments cliquables que sur une classe précise', function () {
    $classe = assiduiteJeuDEssai();

    expect(assiduiteAppel(assiduiteWidget('bar'), 'getSegmentUrls'))->toBe([])
        ->and(assiduiteAppel(assiduiteWidget('bar', $classe->id), 'getSegmentUrls'))->toHaveCount(3);
});

it('affiche le sélecteur de forme et le filtre de classe dans le widget', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    $classe = assiduiteJeuDEssai();

    Livewire::test(AssiduiteRepartitionChart::class)
        ->assertSuccessful()
        ->assertSee('Barres')
        ->assertSee('Camembert')
        ->assertSee('Anneau')
        // Choix d'une classe : le titre suit.
        ->set('filters.classe', $classe->id)
        ->assertSee($classe->nom_complet);
});
