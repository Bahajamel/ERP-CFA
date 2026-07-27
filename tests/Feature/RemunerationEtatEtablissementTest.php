<?php

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\User;
use App\Support\EntrepriseAnnuaire;
use App\Support\RemunerationApprenti;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function remunerationUser(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administratif']);

    return $u;
}

/*
|--------------------------------------------------------------------------
| F-16 — Rémunération légale de l'apprenti (grille D6222-26, % du SMIC)
|--------------------------------------------------------------------------
*/

it('applique la grille légale : taux par tranche d\'âge et année d\'exécution', function () {
    // Moins de 18 ans.
    expect(RemunerationApprenti::taux(17, 1))->toBe(27)
        ->and(RemunerationApprenti::taux(17, 3))->toBe(55)
        // 18 à 20 ans.
        ->and(RemunerationApprenti::taux(19, 1))->toBe(43)
        ->and(RemunerationApprenti::taux(20, 2))->toBe(51)
        // 21 à 25 ans.
        ->and(RemunerationApprenti::taux(22, 1))->toBe(53)
        ->and(RemunerationApprenti::taux(24, 3))->toBe(78)
        // 26 ans et plus : 100 % quelle que soit l'année.
        ->and(RemunerationApprenti::taux(26, 1))->toBe(100)
        ->and(RemunerationApprenti::taux(30, 2))->toBe(100)
        // Au-delà de 3 ans : plancher de la 3e année.
        ->and(RemunerationApprenti::taux(17, 4))->toBe(55);
});

it('sélectionne le SMIC en vigueur à chaque date (barème daté)', function () {
    expect(RemunerationApprenti::smicMensuelBrut(Carbon::parse('2026-07-01')))->toBe(1867.02)
        ->and(RemunerationApprenti::smicMensuelBrut(Carbon::parse('2026-03-15')))->toBe(1823.03)
        ->and(RemunerationApprenti::smicMensuelBrut(Carbon::parse('2025-06-01')))->toBe(1801.80)
        // Avant la première valeur connue : celle-ci sert d'approximation.
        ->and(RemunerationApprenti::smicMensuelBrut(Carbon::parse('2023-01-01')))->toBe(1801.80);
});

it('découpe le contrat en périodes : année d\'exécution et passage de tranche d\'âge', function () {
    // Apprenti né le 15/06/2007 : 19 ans au début du contrat, 21 ans le
    // 15/06/2028 → majoration au 1er juillet 2028 (1er jour du mois suivant).
    $periodes = RemunerationApprenti::periodes('2007-06-15', '2026-09-01', '2028-08-31');

    expect($periodes)->toHaveCount(3);

    // Année 1, 19 ans → 43 % du SMIC (1 867,02 €) = 802,82 €.
    expect($periodes[0]['du']->toDateString())->toBe('2026-09-01')
        ->and($periodes[0]['au']->toDateString())->toBe('2027-08-31')
        ->and($periodes[0]['annee'])->toBe(1)
        ->and($periodes[0]['age'])->toBe(19)
        ->and($periodes[0]['taux'])->toBe(43)
        ->and($periodes[0]['montant'])->toBe(802.82);

    // Année 2, 20 ans → 51 %.
    expect($periodes[1]['du']->toDateString())->toBe('2027-09-01')
        ->and($periodes[1]['au']->toDateString())->toBe('2028-06-30')
        ->and($periodes[1]['annee'])->toBe(2)
        ->and($periodes[1]['taux'])->toBe(51);

    // Toujours année 2 mais 21 ans révolus → 61 % dès le 1er juillet.
    expect($periodes[2]['du']->toDateString())->toBe('2028-07-01')
        ->and($periodes[2]['age'])->toBe(21)
        ->and($periodes[2]['taux'])->toBe(61)
        ->and($periodes[2]['montant'])->toBe(1138.88);
});

it('applique 100 % du SMIC aux apprentis de 26 ans et plus', function () {
    $periodes = RemunerationApprenti::periodes('1995-01-10', '2026-09-01', '2027-08-31');

    expect($periodes)->toHaveCount(1)
        ->and($periodes[0]['taux'])->toBe(100)
        ->and($periodes[0]['montant'])->toBe(1867.02);
});

it('reste silencieux sur des dates manquantes ou invalides', function () {
    expect(RemunerationApprenti::periodes(null, '2026-09-01', '2028-08-31'))->toBe([])
        ->and(RemunerationApprenti::periodes('pas-une-date', '2026-09-01', '2028-08-31'))->toBe([])
        // Fin avant début : aucun barème.
        ->and(RemunerationApprenti::periodes('2007-06-15', '2028-08-31', '2026-09-01'))->toBe([])
        ->and(RemunerationApprenti::minimum(null, null, null))->toBeNull();
});

it('calcule le plancher applicable à une date de référence', function () {
    // En cours de contrat : période contenant la référence.
    $min = RemunerationApprenti::minimum('2007-06-15', '2026-09-01', '2028-08-31', '2026-10-01');

    expect($min['taux'])->toBe(43)->and($min['montant'])->toBe(802.82);

    // Avant le début du contrat : première période.
    $avant = RemunerationApprenti::minimum('2007-06-15', '2026-09-01', '2028-08-31', '2026-01-01');

    expect($avant['taux'])->toBe(43);
});

/**
 * La rémunération (barème légal, pré-remplissage et garde du plancher) se
 * complète sur la fiche du dossier — c.-à-d. la page d'édition. La création
 * initiale passe désormais par l'assistant en 4 étapes, qui ne collecte pas le
 * salaire (voir ContractDossierWizardTest).
 */
function contratAComplete(Candidate $candidate, ?float $salaire = null): Contract
{
    $company = Company::factory()->create();
    $tuteur = CompanyContact::factory()->create(['company_id' => $company->id, 'is_tuteur' => true]);
    $formation = Formation::factory()->create();

    return Contract::factory()->create([
        'candidate_id' => $candidate->id,
        'company_id' => $company->id,
        'formation_id' => $formation->id,
        'tuteur_id' => $tuteur->id,
        'code_rncp' => 'RNCP34567',
        'rythme' => '2 j CFA / 3 j entreprise',
        'date_debut' => now()->addMonths(2)->format('Y-m-d'),
        'date_fin' => now()->addMonths(26)->format('Y-m-d'),
        'lieu_formation' => 'CFA de Lyon',
        'salaire_mensuel_brut' => $salaire,
        'statut_contrat' => ContractStatut::EnCours,
    ]);
}

it('bloque un salaire sous le minimum légal dans la fiche du contrat', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(remunerationUser());

    $candidate = Candidate::factory()->create(['date_naissance' => now()->subYears(19)->format('Y-m-d')]);
    $contract = contratAComplete($candidate);

    // 19 ans, 1re année → minimum 43 % du SMIC ≈ 802,82 € : 500 € est illégal.
    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['salaire_mensuel_brut' => 500])
        ->call('save')
        ->assertHasFormErrors(['salaire_mensuel_brut']);

    expect((float) $contract->refresh()->salaire_mensuel_brut)->not->toBe(500.0);
});

it('accepte un salaire conforme et pré-remplit le minimum légal quand il est vide', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(remunerationUser());

    $candidate = Candidate::factory()->create(['date_naissance' => now()->subYears(19)->format('Y-m-d')]);
    $contract = contratAComplete($candidate, salaire: null);

    // Salaire non saisi : toucher aux dates déclenche le pré-remplissage du
    // minimum légal (43 % du SMIC en vigueur), que l'enregistrement conserve.
    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->set('data.date_debut', now()->addMonths(2)->format('Y-m-d'))
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $contract->refresh()->salaire_mensuel_brut)->toBe(802.82);
});

/*
|--------------------------------------------------------------------------
| F-09 — Alerte établissement fermé (Annuaire des Entreprises)
|--------------------------------------------------------------------------
*/

it('signale un établissement fermé dans la fiche et le libellé de recherche', function () {
    Http::fake([
        'recherche-entreprises.api.gouv.fr/*' => Http::response([
            'results' => [[
                'nom_raison_sociale' => 'ANCIENNE FORGE',
                'etat_administratif' => 'A',
                'siege' => [
                    'siret' => '12345678900012',
                    'etat_administratif' => 'F',
                    'date_fermeture' => '2024-05-31',
                    'adresse' => '2 RUE DES FORGES 42000 SAINT-ETIENNE',
                    'code_postal' => '42000',
                    'libelle_commune' => 'SAINT-ETIENNE',
                ],
            ]],
        ]),
    ]);

    $options = app(EntrepriseAnnuaire::class)->options('ANCIENNE FORGE');
    $fiche = EntrepriseAnnuaire::decode(array_key_first($options));

    expect(EntrepriseAnnuaire::ferme($fiche))->toBeTrue()
        ->and($fiche['date_fermeture'])->toBe('2024-05-31')
        // L'utilisateur est prévenu dès la liste de résultats.
        ->and(reset($options))->toContain('Fermé')
        ->and($fiche['label'])->toContain('Fermé');
});

it('détecte l\'état d\'un établissement par SIRET (fermé, actif, cessation entreprise)', function () {
    Http::fake([
        'recherche-entreprises.api.gouv.fr/*' => Http::sequence()
            // Établissement fermé (état « F » sur l'établissement).
            ->push(['results' => [[
                'etat_administratif' => 'A',
                'siege' => ['siret' => '00000000000000'],
                'matching_etablissements' => [[
                    'siret' => '12345678900012',
                    'etat_administratif' => 'F',
                    'date_fermeture' => '2023-12-31',
                ]],
            ]]])
            // Établissement actif.
            ->push(['results' => [[
                'etat_administratif' => 'A',
                'siege' => ['siret' => '12345678900012', 'etat_administratif' => 'A', 'date_fermeture' => null],
            ]]])
            // Unité légale cessée (« C ») : fermé même si l'établissement est « A ».
            ->push(['results' => [[
                'etat_administratif' => 'C',
                'siege' => ['siret' => '12345678900012', 'etat_administratif' => 'A', 'date_fermeture' => null],
            ]]])
            // SIRET introuvable.
            ->push(['results' => []]),
    ]);

    $annuaire = app(EntrepriseAnnuaire::class);

    expect($annuaire->etatSiret('123 456 789 00012'))->toBe(['ferme' => true, 'date_fermeture' => '2023-12-31'])
        ->and($annuaire->etatSiret('12345678900012'))->toBe(['ferme' => false, 'date_fermeture' => null])
        ->and($annuaire->etatSiret('12345678900012'))->toBe(['ferme' => true, 'date_fermeture' => null])
        ->and($annuaire->etatSiret('12345678900012'))->toBeNull();
});

it('ne consulte pas l\'Annuaire pour un SIRET invalide', function () {
    Http::fake();

    expect(app(EntrepriseAnnuaire::class)->etatSiret('123'))->toBeNull()
        ->and(EntrepriseAnnuaire::ferme(null))->toBeFalse();

    Http::assertNothingSent();
});
