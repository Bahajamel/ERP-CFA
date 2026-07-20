<?php

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Models\CustomTable;
use App\Models\User;
use App\Support\BoardNavigation;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

/** Simule la route Filament courante par son nom (pour la détection de contexte). */
function bnRoute(string $name): void
{
    $route = new Route('GET', '/x', []);
    $route->name($name);
    request()->setRouteResolver(fn () => $route);
}

it('s\'affiche sur les modules à boards et les pages de tableaux personnalisés', function () {
    expect(BoardNavigation::doitAfficherPour(CandidateResource::getRouteBaseName()))->toBeTrue()
        ->and(BoardNavigation::doitAfficherPour(CompanyResource::getRouteBaseName().'.edit'))->toBeTrue()
        ->and(BoardNavigation::doitAfficherPour(NeedResource::getRouteBaseName()))->toBeTrue()
        ->and(BoardNavigation::doitAfficherPour(CustomTableResource::getRouteBaseName().'.edit'))->toBeTrue()
        ->and(BoardNavigation::doitAfficherPour('filament.admin.resources.contracts.index'))->toBeFalse()
        ->and(BoardNavigation::doitAfficherPour(null))->toBeFalse();
});

it('sur Candidats : liste Base Candidats + les tableaux du contexte candidat (seulement)', function () {
    CustomTable::create(['name' => 'Vivier alternance', 'context' => 'candidate']);
    CustomTable::create(['name' => 'Salon emploi 2026', 'context' => 'candidate']);
    CustomTable::create(['name' => 'Prospects entreprises', 'context' => 'company']);
    CustomTable::create(['name' => 'Table autonome']); // sans contexte

    bnRoute(CandidateResource::getRouteBaseName());

    $labels = collect(BoardNavigation::boards())->pluck('label')->all();

    expect(BoardNavigation::contexteCourant())->toBe('candidate')
        ->and($labels)->toContain('Base Candidats')
        ->toContain('Vivier alternance')
        ->toContain('Salon emploi 2026')
        ->not->toContain('Prospects entreprises')   // autre module
        ->not->toContain('Table autonome');         // sans module
});

it('sur Entreprises : liste Base Entreprises + les tableaux du contexte entreprise', function () {
    CustomTable::create(['name' => 'Prospects entreprises', 'context' => 'company']);
    CustomTable::create(['name' => 'Vivier alternance', 'context' => 'candidate']);

    bnRoute(CompanyResource::getRouteBaseName());

    $labels = collect(BoardNavigation::boards())->pluck('label')->all();

    expect(BoardNavigation::contexteCourant())->toBe('company')
        ->and($labels)->toContain('Base Entreprises')
        ->toContain('Prospects entreprises')
        ->not->toContain('Vivier alternance');
});

it('exclut les tableaux archivés du sélecteur', function () {
    CustomTable::create(['name' => 'Actif', 'context' => 'candidate']);
    CustomTable::create(['name' => 'Archivé', 'context' => 'candidate', 'is_active' => false]);

    bnRoute(CandidateResource::getRouteBaseName());

    $labels = collect(BoardNavigation::boards())->pluck('label')->all();

    expect($labels)->toContain('Actif')->not->toContain('Archivé');
});

it('adapte le libellé du lien public au module du tableau', function () {
    expect(BoardNavigation::libelleLien('candidate'))->toBe('Lien de candidature')
        ->and(BoardNavigation::libelleLien('company'))->toBe('Lien entreprise')
        ->and(BoardNavigation::libelleLien('need'))->toBe('Lien offre')
        ->and(BoardNavigation::libelleLien(null))->toBe('Lien du formulaire');
});

it('reprend la charte du module pour le formulaire public (entreprise vs candidat)', function () {
    $entreprise = BoardNavigation::themePublic('company');
    $candidat = BoardNavigation::themePublic('candidate');

    // Un tableau Entreprises reprend l'habillage du formulaire entreprise existant.
    expect($entreprise['image'])->toBe('partenaire.jfif')
        ->and($entreprise['badge'])->toBe('CFA · Entreprise partenaire')
        // …distinct du thème Candidats.
        ->and($candidat['image'])->toBe('Rejoignez-nous2.jpg')
        ->and($candidat['badge'])->toBe('CFA · Candidature');
});

it('réserve « Nouveau tableau » à qui a la permission de créer une table', function () {
    expect(BoardNavigation::peutCreer())->toBeTrue(); // Administrateur

    // Commercial pilote ses tableaux → il peut créer.
    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial');
    $this->actingAs($commercial);
    expect(BoardNavigation::peutCreer())->toBeTrue();

    // Un rôle hors périmètre (ex. Formateur) ne peut pas.
    $formateur = User::factory()->create(['is_active' => true]);
    $formateur->syncRoles('Formateur');
    $this->actingAs($formateur);
    expect(BoardNavigation::peutCreer())->toBeFalse();
});

it('n\'expose aucun tableau personnalisé à un rôle sans droit de les voir', function () {
    CustomTable::create(['name' => 'Confidentiel', 'context' => 'candidate']);

    // Admission voit les Candidats mais n'a pas custom_tables.view.
    $admission = User::factory()->create(['is_active' => true]);
    $admission->syncRoles('Admission');
    $this->actingAs($admission);

    bnRoute(CandidateResource::getRouteBaseName());

    $labels = collect(BoardNavigation::boards())->pluck('label')->all();

    expect($labels)->toContain('Base Candidats')      // module autorisé
        ->not->toContain('Confidentiel');             // tables custom masquées
});
