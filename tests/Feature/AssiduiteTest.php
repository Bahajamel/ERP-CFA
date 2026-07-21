<?php

use App\Enums\PresenceStatut;
use App\Filament\Pages\Assiduite;
use App\Filament\Widgets\AssiduiteRepartitionChart;
use App\Models\Candidate;
use App\Models\Formation;
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

/** Widget configuré sur un type de graphe et, éventuellement, une classe. */
function assiduiteWidget(string $forme = 'bar', ?int $classeId = null): AssiduiteRepartitionChart
{
    $widget = new AssiduiteRepartitionChart;
    $widget->filter = $forme;
    $widget->filters = ['promotion' => $classeId];

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

it('n\'agrège jamais plusieurs classes : chaque classe a ses propres chiffres', function () {
    $classeA = assiduiteJeuDEssai();                       // 3 séances émargées

    // Une seconde classe, dans une autre formation, entièrement présente.
    $classeB = Promotion::factory()->create();
    $cB = Candidate::factory()->dansClasse($classeB)->create();
    emarger(Seance::factory()->create(['promotion_id' => $classeB->id, 'date' => '2026-09-01']), $cB, PresenceStatut::Present);

    $surA = assiduiteAppel(assiduiteWidget('bar', $classeA->id), 'getData');
    $surB = assiduiteAppel(assiduiteWidget('bar', $classeB->id), 'getData');

    // Chaque vue reste sur sa classe : jamais 4 (= 3 + 1).
    expect(array_sum($surA['datasets'][0]['data']))->toBe(3)
        ->and($surB['labels'])->toBe(['Présents'])
        ->and($surB['datasets'][0]['data'])->toBe([1]);
});

it('présélectionne une classe qui a des émargements', function () {
    // Une classe sans aucun émargement, et une qui en a.
    Promotion::factory()->create(['annee_scolaire' => '2030-2031']);
    $emargee = assiduiteJeuDEssai();

    expect(AssiduiteRepartitionChart::classeParDefaut()?->id)->toBe($emargee->id);
});

it('affiche la classe et son taux de présence en en-tête', function () {
    $classe = assiduiteJeuDEssai(); // présent + retard comptent présents → 2/3

    $widget = assiduiteWidget('bar', $classe->id);

    expect($widget->getHeading())->toBe('Assiduité — '.$classe->nom_complet)
        ->and($widget->getDescription())->toContain('67 %');
});

it('rend les segments cliquables vers l\'émargement de la classe', function () {
    $classe = assiduiteJeuDEssai();

    expect(assiduiteAppel(assiduiteWidget('bar', $classe->id), 'getSegmentUrls'))->toHaveCount(3);
});

it('affiche les listes Formation / Classe et le choix du type de graphe', function () {
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
        ->assertSee('Formation')
        ->assertSee('Classe')
        // Les listes sont rendues EN CLAIR au-dessus du graphique (conteneur
        // dédié), et non cachées derrière l'icône entonnoir de Filament.
        ->assertSee('fi-wi-chart-scope', false)
        // Ouverture directe sur une classe précise, pas sur un agrégat.
        ->assertSee($classe->nom_complet);
});

it('ne propose que les classes de la formation choisie', function () {
    $formationA = Formation::factory()->create();
    $formationB = Formation::factory()->create();
    $classeA = Promotion::factory()->create(['formation_id' => $formationA->id]);
    $classeB = Promotion::factory()->create(['formation_id' => $formationB->id]);

    expect(array_keys(AssiduiteRepartitionChart::classesDe($formationA->id)))->toBe([$classeA->id])
        ->and(array_keys(AssiduiteRepartitionChart::classesDe($formationB->id)))->toBe([$classeB->id])
        // Sans formation : aucune classe (jamais celles d'une autre formation).
        ->and(AssiduiteRepartitionChart::classesDe(null))->toBe([]);
});

it('repositionne la classe quand on change de formation', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    $formationB = Formation::factory()->create();
    $classeB = Promotion::factory()->create(['formation_id' => $formationB->id]);
    assiduiteJeuDEssai(); // une autre formation, présélectionnée au départ

    Livewire::test(AssiduiteRepartitionChart::class)
        ->set('filters.formation', $formationB->id)
        // La classe AFFICHÉE suit la formation, sinon on montrerait les chiffres
        // d'une classe d'une autre formation.
        ->assertSee($classeB->nom_complet);
});

it('ne montre jamais une classe étrangère à la formation choisie', function () {
    $formationB = Formation::factory()->create();
    $classeB = Promotion::factory()->create(['formation_id' => $formationB->id]);
    $classeA = assiduiteJeuDEssai(); // autre formation, avec des émargements

    // Formation B choisie mais classe A encore en mémoire → on retombe sur B.
    $widget = new AssiduiteRepartitionChart;
    $widget->filters = ['formation' => $formationB->id, 'promotion' => $classeA->id];

    expect($widget->getHeading())->toBe('Assiduité — '.$classeB->nom_complet);
});

it('recrée le graphique quand on change de forme (camembert, anneau)', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    assiduiteJeuDEssai();

    // La clé porte le type : c'est elle qui force Chart.js à se recréer malgré
    // le wire:ignore (sans quoi la forme resterait bloquée sur « barres »).
    Livewire::test(AssiduiteRepartitionChart::class)
        ->assertSee('assiduite-repartition-bar', false)
        ->set('filter', 'pie')
        ->assertSee('assiduite-repartition-pie', false)
        ->assertSee('data-chart-type="pie"', false)
        ->set('filter', 'doughnut')
        ->assertSee('assiduite-repartition-doughnut', false)
        ->assertSee('data-chart-type="doughnut"', false)
        ->assertDontSee('assiduite-repartition-bar', false);
});

it('met à jour les OPTIONS de la liste Classe quand on change de formation', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Scolarité');
    $this->actingAs($user);

    $formationB = Formation::factory()->create();
    $annee = ['annee_scolaire' => '2025-2026', 'formation_id' => $formationB->id];
    $premiere = Promotion::factory()->create($annee + ['libelle' => '1ère année']);
    $deuxieme = Promotion::factory()->create($annee + ['libelle' => '2ème année']);

    $classeAutreFormation = assiduiteJeuDEssai(); // présélectionnée au départ

    Livewire::test(AssiduiteRepartitionChart::class)
        ->set('filters.formation', $formationB->id)
        // Les DEUX années de la formation choisie doivent figurer dans la liste.
        // « 2ème année » n'est pas la classe affichée : si on la voit, c'est
        // bien qu'elle est proposée en option.
        ->assertSee($premiere->nom_complet)
        ->assertSee($deuxieme->nom_complet)
        // …et aucune classe d'une autre formation.
        ->assertDontSee($classeAutreFormation->nom_complet);
});
