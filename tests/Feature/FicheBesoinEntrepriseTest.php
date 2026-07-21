<?php

use App\Enums\NeedOrigine;
use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Filament\Resources\Needs\Pages\NeedsKanban;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Need;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Étape 1 franchie : l'entreprise du parcours est en session. */
function parcoursEntreprise(array $attributs = []): Company
{
    $company = Company::factory()->create($attributs);
    $company->contacts()->create(['nom' => 'Dupont', 'email' => 'rh@acme.test', 'is_principal' => true]);

    test()->withSession(['entreprise_partenaire_id' => $company->id]);

    return $company;
}

// ── Parcours public en deux étapes ────────────────────────────────────────────

it('enchaîne sur la fiche besoin après l\'enregistrement de l\'entreprise', function () {
    $this->post(route('entreprise.store'), [
        'raison_sociale' => 'ACME SA',
        'siret' => '12345678900011',
        'contact_nom' => 'Dupont',
        'contact_email' => 'rh@acme.test',
    ])->assertRedirect(route('entreprise.besoin'));

    // L'entreprise créée est retenue en session pour l'étape 2.
    expect(session('entreprise_partenaire_id'))
        ->toBe(Company::where('siret', '12345678900011')->value('id'));
});

it('affiche la fiche besoin avec l\'entreprise et les formations du CFA', function () {
    $company = parcoursEntreprise(['raison_sociale' => 'ACME SA']);
    Formation::factory()->create([
        'libelle' => 'Boulangerie CAP',
        'organisation_id' => $company->organisation_id,
    ]);

    $this->get(route('entreprise.besoin'))
        ->assertOk()
        ->assertSee('Décrivez votre besoin')
        ->assertSee('ACME SA')
        ->assertSee('Boulangerie CAP');
});

it('renvoie vers l\'étape 1 quand la session a expiré', function () {
    $this->get(route('entreprise.besoin'))
        ->assertRedirect(route('entreprise.create'))
        ->assertSessionHas('expire');

    $this->post(route('entreprise.besoin.store'), ['intitule_poste' => 'Vendeur', 'nb_postes' => 1])
        ->assertRedirect(route('entreprise.create'));

    expect(Need::count())->toBe(0);
});

it('crée l\'offre depuis la fiche besoin, rattachée à l\'entreprise et à son contact', function () {
    $company = parcoursEntreprise(['adresse' => '1 rue de la Paix, 75001 Paris']);
    $formation = Formation::factory()->create(['organisation_id' => $company->organisation_id]);

    $this->post(route('entreprise.besoin.store'), [
        'intitule_poste' => 'Apprenti boulanger',
        'formation_id' => $formation->id,
        'nb_postes' => 2,
        'date_demarrage' => now()->addMonth()->toDateString(),
        'rythme' => '2 j CFA / 3 j entreprise',
        'prerequis' => 'Ponctualité, goût du travail en équipe.',
    ])->assertRedirect(route('entreprise.merci'));

    $need = Need::firstOrFail();

    expect($need->intitule_poste)->toBe('Apprenti boulanger')
        ->and($need->company_id)->toBe($company->id)
        ->and($need->formation_id)->toBe($formation->id)
        ->and($need->nb_postes)->toBe(2)
        ->and($need->contact_id)->toBe($company->contactPrincipal->first()->id)
        // Lieu non saisi → l'adresse de l'entreprise sert de repli.
        ->and($need->localisation)->toBe('1 rue de la Paix, 75001 Paris')
        ->and($need->organisation_id)->toBe($company->organisation_id)
        ->and($need->origine)->toBe(NeedOrigine::Entreprise)
        ->and($need->statut)->toBe(NeedStatut::Cree);

    // Parcours terminé : la session ne permet plus de redéposer un besoin.
    expect(session('entreprise_partenaire_id'))->toBeNull();
});

it('valide la fiche besoin : intitulé requis, date non passée, postes ≥ 1', function () {
    parcoursEntreprise();

    $this->post(route('entreprise.besoin.store'), ['nb_postes' => 1])
        ->assertSessionHasErrors('intitule_poste');

    $this->post(route('entreprise.besoin.store'), [
        'intitule_poste' => 'Vendeur',
        'nb_postes' => 1,
        'date_demarrage' => now()->subWeek()->toDateString(),
    ])->assertSessionHasErrors('date_demarrage');

    $this->post(route('entreprise.besoin.store'), ['intitule_poste' => 'Vendeur', 'nb_postes' => 0])
        ->assertSessionHasErrors('nb_postes');

    expect(Need::count())->toBe(0);
});

it('nettoie les balises HTML de la fiche besoin (anti-XSS)', function () {
    parcoursEntreprise();

    $this->post(route('entreprise.besoin.store'), [
        'intitule_poste' => 'Vendeur <script>alert(1)</script>',
        'nb_postes' => 1,
    ])->assertRedirect(route('entreprise.merci'));

    expect(Need::value('intitule_poste'))->toBe('Vendeur alert(1)');
});

it('ignore le dépôt quand le pot de miel est rempli', function () {
    parcoursEntreprise();

    $this->post(route('entreprise.besoin.store'), [
        'intitule_poste' => 'Vendeur',
        'nb_postes' => 1,
        'website' => 'https://spam.test',
    ])->assertRedirect(route('entreprise.merci'));

    expect(Need::count())->toBe(0);
});

it('prévient les commerciaux du CFA par notification interne', function () {
    $this->seed(RolePermissionSeeder::class);

    // Le CFA est celui posé par TestCase (tenant courant) : en créer un autre
    // rendrait l'entreprise invisible sous le cloisonnement multi-CFA.
    $organisation = Filament::getTenant();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial'); // dispose de access_needs
    $commercial->organisations()->syncWithoutDetaching([$organisation->getKey()]);

    parcoursEntreprise();

    $this->post(route('entreprise.besoin.store'), ['intitule_poste' => 'Vendeur conseil', 'nb_postes' => 1])
        ->assertRedirect(route('entreprise.merci'));

    expect($commercial->notifications()->count())->toBe(1)
        ->and($commercial->notifications()->first()->data['title'] ?? '')
        ->toContain('Vendeur conseil');
});

// ── Circuit de validation ─────────────────────────────────────────────────────

it('tient l\'offre déposée hors du circuit de recrutement tant qu\'elle n\'est pas validée', function () {
    $depot = Need::factory()->create([
        'statut' => NeedStatut::Cree,
        'origine' => NeedOrigine::Entreprise,
        'validee_at' => null,
    ]);

    expect($depot->attendValidation())->toBeTrue()
        ->and(Need::ouverts()->pluck('id'))->not->toContain($depot->id)
        ->and(Need::enAttenteDeValidation()->pluck('id'))->toContain($depot->id);

    $depot->validerDepotEntreprise();

    expect($depot->fresh()->attendValidation())->toBeFalse()
        ->and(Need::ouverts()->pluck('id'))->toContain($depot->id);
});

it('laisse les offres saisies par le CFA dans le circuit (aucune régression)', function () {
    $interne = Need::factory()->create(['statut' => NeedStatut::Cree]);

    expect($interne->origine)->toBe(NeedOrigine::Interne)
        ->and($interne->attendValidation())->toBeFalse()
        ->and(Need::ouverts()->pluck('id'))->toContain($interne->id);
});

it('sort une offre rejetée de la file d\'attente', function () {
    $depot = Need::factory()->create([
        'statut' => NeedStatut::Annule,
        'origine' => NeedOrigine::Entreprise,
        'validee_at' => null,
    ]);

    // Annulée sans jamais être validée : elle ne doit pas rester « à valider ».
    expect($depot->attendValidation())->toBeFalse()
        ->and(Need::enAttenteDeValidation()->pluck('id'))->not->toContain($depot->id);
});

it('ne redate pas une validation déjà faite', function () {
    $depot = Need::factory()->create([
        'statut' => NeedStatut::Cree,
        'origine' => NeedOrigine::Entreprise,
        'validee_at' => now()->subDays(3),
    ]);

    $avant = $depot->validee_at;
    $depot->validerDepotEntreprise();

    expect($depot->fresh()->validee_at->timestamp)->toBe($avant->timestamp);
});

// ── Côté ERP ──────────────────────────────────────────────────────────────────

describe('liste des offres', function () {
    beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles('Commercial');
        $this->actingAs($user);
    });

    it('compte les besoins à valider et les isole via le filtre rapide', function () {
        $depot = Need::factory()->create([
            'statut' => NeedStatut::Cree,
            'origine' => NeedOrigine::Entreprise,
            'validee_at' => null,
        ]);
        $interne = Need::factory()->create(['statut' => NeedStatut::Cree]);

        $page = Livewire::test(ListNeeds::class);

        expect($page->instance()->getQuickCounts()['a_valider'])->toBe(1);

        $page->call('setQuickScope', 'a_valider')
            ->assertCanSeeTableRecords([$depot])
            ->assertCanNotSeeTableRecords([$interne]);
    });

    it('valide un besoin déposé depuis la liste', function () {
        $depot = Need::factory()->create([
            'statut' => NeedStatut::Cree,
            'origine' => NeedOrigine::Entreprise,
            'validee_at' => null,
        ]);

        Livewire::test(ListNeeds::class)
            ->callTableAction('validerDepot', $depot)
            ->assertNotified();

        expect($depot->fresh()->validee_at)->not->toBeNull()
            ->and(Need::ouverts()->pluck('id'))->toContain($depot->id);
    });

    it('rejette un besoin déposé avec un motif', function () {
        $depot = Need::factory()->create([
            'statut' => NeedStatut::Cree,
            'origine' => NeedOrigine::Entreprise,
            'validee_at' => null,
        ]);

        Livewire::test(ListNeeds::class)
            ->callTableAction('rejeterDepot', $depot, ['comment' => 'Doublon'])
            ->assertNotified();

        expect($depot->fresh()->statut)->toBe(NeedStatut::Annule)
            ->and($depot->fresh()->attendValidation())->toBeFalse();
    });

    it('n\'affiche pas les besoins en attente dans le pipeline', function () {
        $depot = Need::factory()->create([
            'statut' => NeedStatut::Cree,
            'origine' => NeedOrigine::Entreprise,
            'validee_at' => null,
        ]);
        Need::factory()->create(['statut' => NeedStatut::Cree]);

        $colonnes = Livewire::test(NeedsKanban::class)->instance()->getColumns();
        $cree = collect($colonnes)->firstWhere('statut', NeedStatut::Cree);

        expect($cree['needs']->pluck('id'))->not->toContain($depot->id)
            ->and($cree['needs'])->toHaveCount(1);
    });
});
