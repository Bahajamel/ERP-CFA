<?php

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Corbeille\CorbeilleResource;
use App\Filament\Resources\Entretiens\EntretienResource;
use App\Filament\Resources\Formations\FormationResource;
use App\Filament\Resources\Matchings\MatchingResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Support\CommercialNavigation as Nav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;

// RefreshDatabase migre la base : le TestCase peut alors fixer le tenant + le
// panel « admin », nécessaires à getRouteBaseName()/getUrl().
uses(RefreshDatabase::class);

it('affiche la barre sur toutes les rubriques du module Commercial', function () {
    foreach ([
        CandidateResource::class,
        CompanyResource::class,
        NeedResource::class,
        EntretienResource::class,
        MatchingResource::class,
        CorbeilleResource::class,
    ] as $resource) {
        expect(Nav::doitAfficherPour($resource::getRouteBaseName().'.index'))
            ->toBeTrue("La barre devrait s'afficher pour {$resource}");
    }
});

it('masque la barre hors du module Commercial', function () {
    expect(Nav::doitAfficherPour(FormationResource::getRouteBaseName().'.index'))->toBeFalse()
        ->and(Nav::doitAfficherPour('filament.admin.pages.dashboard'))->toBeFalse()
        ->and(Nav::doitAfficherPour(null))->toBeFalse();
});

it('active l\'onglet correspondant à la ressource consultée', function () {
    $candidats = CandidateResource::getRouteBaseName();

    expect(Nav::estActifPour(CandidateResource::class, $candidats.'.index'))->toBeTrue()
        ->and(Nav::estActifPour(CompanyResource::class, $candidats.'.index'))->toBeFalse()
        ->and(Nav::estActifPour(NeedResource::class, $candidats.'.index'))->toBeFalse();
});

it('garde l\'onglet actif sur les sous-pages (création, modification, détail, relations)', function () {
    $entreprises = CompanyResource::getRouteBaseName();

    foreach (['create', 'edit', 'view', 'besoins', 'contacts'] as $sousPage) {
        expect(Nav::estActifPour(CompanyResource::class, $entreprises.'.'.$sousPage))
            ->toBeTrue("L'onglet Entreprises devrait rester actif sur .{$sousPage}");
    }
});

it('n\'active aucun onglet sur Entretiens, Matching ou Corbeille', function () {
    foreach ([EntretienResource::class, MatchingResource::class, CorbeilleResource::class] as $neutre) {
        $route = $neutre::getRouteBaseName().'.index';

        foreach (Nav::tabs() as $onglet) {
            expect(Nav::estActifPour($onglet['resource'], $route))
                ->toBeFalse("Aucun onglet ne doit être actif sur {$neutre}");
        }

        // …mais la barre reste bien visible.
        expect(Nav::doitAfficherPour($route))->toBeTrue();
    }
});

it('expose exactement les trois onglets attendus, dans l\'ordre', function () {
    expect(collect(Nav::tabs())->pluck('key')->all())->toBe(['candidates', 'companies', 'offers'])
        ->and(collect(Nav::tabs())->pluck('label')->all())->toBe(['Candidats', 'Entreprises', 'Offres']);
});

it('pointe chaque onglet vers la page principale (index) de sa ressource', function () {
    expect(Nav::url(CandidateResource::class))->toBe(CandidateResource::getUrl('index'))
        ->and(Nav::url(CompanyResource::class))->toBe(CompanyResource::getUrl('index'))
        ->and(Nav::url(NeedResource::class))->toBe(NeedResource::getUrl('index'));
});

/** Fixe la route courante de la requête (pour piloter le rendu de la vue). */
function simulerRoute(?string $nom): void
{
    $route = (new Route(['GET'], '/_test', []));

    if ($nom !== null) {
        $route->name($nom);
    }

    request()->setRouteResolver(fn () => $route);
}

it('rend la barre avec les trois onglets et un onglet actif sur une page Commercial', function () {
    simulerRoute(CandidateResource::getRouteBaseName().'.index');

    $html = view('filament.commercial-subnav')->render();

    expect($html)->toContain('Navigation Commercial')          // <nav aria-label>
        ->and($html)->toContain('Candidats')
        ->and($html)->toContain('Entreprises')
        ->and($html)->toContain('Offres')
        ->and($html)->toContain('aria-current="page"')          // onglet actif marqué
        ->and($html)->toContain('cfa-subnav-link actif');
});

it('ne rend rien hors du module Commercial', function () {
    simulerRoute(FormationResource::getRouteBaseName().'.index');

    expect(trim(view('filament.commercial-subnav')->render()))->toBe('');
});

it('rend la barre sans onglet actif sur Matching (page Commercial neutre)', function () {
    simulerRoute(MatchingResource::getRouteBaseName().'.index');

    $html = view('filament.commercial-subnav')->render();

    expect($html)->toContain('Navigation Commercial')           // barre visible
        ->and($html)->not->toContain('aria-current="page"')     // mais aucun onglet actif
        ->and($html)->not->toContain('cfa-subnav-link actif');
});
