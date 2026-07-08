<?php

use App\Enums\CandidateStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Enums\OpcoStatut;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function cycle(): CycleApprenant
{
    return app(CycleApprenant::class);
}

function candidatAccepte(): Candidate
{
    return Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
}

function besoinPourCycle(): Need
{
    return Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes]);
}

/*
|--------------------------------------------------------------------------
| Candidat → Matching
|--------------------------------------------------------------------------
*/

it('envoie un candidat accepté vers le Matching (statut initial « En recherche »)', function () {
    $matching = cycle()->envoyerVersMatching(candidatAccepte(), besoinPourCycle());

    expect($matching->statut)->toBe(MatchingStatut::EnRecherche)
        ->and($matching->origine)->toBe(CycleApprenant::ORIGINE_CFA);
});

it('refuse d\'envoyer un candidat non accepté vers le Matching', function () {
    $candidat = Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);

    expect(fn () => cycle()->envoyerVersMatching($candidat, besoinPourCycle()))
        ->toThrow(CycleBloqueException::class, CycleApprenant::MSG_CANDIDAT_NON_ACCEPTE);
});

it('refuse d\'envoyer un candidat refusé vers le Matching', function () {
    $candidat = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);

    expect(fn () => cycle()->envoyerVersMatching($candidat, besoinPourCycle()))
        ->toThrow(CycleBloqueException::class);
});

it('bloque le doublon de matching pour le même candidat et le même besoin', function () {
    $candidat = candidatAccepte();
    $besoin = besoinPourCycle();

    cycle()->envoyerVersMatching($candidat, $besoin);

    expect(fn () => cycle()->envoyerVersMatching($candidat, $besoin))
        ->toThrow(CycleBloqueException::class, CycleApprenant::MSG_MATCHING_EXISTANT);
});

/*
|--------------------------------------------------------------------------
| Entreprise externe (trouvée par le candidat)
|--------------------------------------------------------------------------
*/

it('gère l\'entreprise trouvée par le candidat : prospect + besoin + matching tracé', function () {
    $candidat = candidatAccepte();

    $matching = cycle()->entrepriseTrouveeParCandidat($candidat, [
        'raison_sociale' => 'Garage Dupont',
        'siret' => '123 456 789 00012',
        'intitule_poste' => 'Apprenti mécanicien',
    ]);

    $company = $matching->need->company;

    expect($matching->origine)->toBe(CycleApprenant::ORIGINE_CANDIDAT)
        ->and($matching->estOrigineCandidat())->toBeTrue()
        ->and($company->raison_sociale)->toBe('Garage Dupont')
        ->and($company->siret)->toBe('12345678900012')
        // Entreprise externe = prospect à qualifier par le CFA.
        ->and($company->statut)->toBe(CompanyStatut::Prospect)
        ->and($matching->need->intitule_poste)->toBe('Apprenti mécanicien');
});

it('réutilise l\'entreprise partenaire existante si le SIRET est déjà connu', function () {
    $existante = Company::factory()->create(['siret' => '98765432100012', 'statut' => CompanyStatut::Partenaire]);

    $matching = cycle()->entrepriseTrouveeParCandidat(candidatAccepte(), [
        'raison_sociale' => 'Autre Nom Saisi',
        'siret' => '987 654 321 00012',
    ]);

    expect($matching->need->company_id)->toBe($existante->id)
        ->and(Company::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Matching accepté → Contrat (prérempli, anti-doublon)
|--------------------------------------------------------------------------
*/

function matchingAccepte(): App\Models\Matching
{
    $formation = Formation::factory()->create();
    $company = Company::factory()->create();
    $tuteur = CompanyContact::factory()->create(['company_id' => $company->id, 'is_tuteur' => true]);
    $besoin = Need::factory()->create([
        'company_id' => $company->id,
        'formation_id' => $formation->id,
        'tuteur_id' => $tuteur->id,
        'statut' => NeedStatut::ProfilsEnvoyes,
    ]);

    $matching = cycle()->envoyerVersMatching(candidatAccepte(), $besoin);
    $matching->forceFill(['statut' => MatchingStatut::Accepte, 'cv_envoye' => true])->save();

    return $matching->fresh();
}

it('crée le contrat prérempli depuis un matching accepté', function () {
    $matching = matchingAccepte();

    $contract = cycle()->creerContratDepuisMatching($matching);

    expect($contract->wasRecentlyCreated)->toBeTrue()
        ->and($contract->candidate_id)->toBe($matching->candidate_id)
        ->and($contract->company_id)->toBe($matching->need->company_id)
        ->and($contract->formation_id)->toBe($matching->need->formation_id)
        ->and($contract->tuteur_id)->toBe($matching->need->tuteur_id)
        ->and($contract->code_rncp)->toBe($matching->need->formation->code_rncp)
        ->and($contract->statut_contrat)->toBe(ContractStatut::Brouillon);
});

it('refuse de créer un contrat depuis un matching non accepté', function () {
    $matching = cycle()->envoyerVersMatching(candidatAccepte(), besoinPourCycle());

    expect(fn () => cycle()->creerContratDepuisMatching($matching))
        ->toThrow(CycleBloqueException::class, CycleApprenant::MSG_MATCHING_NON_ACCEPTE);
});

it('rouvre le contrat actif existant au lieu d\'en créer un doublon', function () {
    $matching = matchingAccepte();

    $premier = cycle()->creerContratDepuisMatching($matching);
    $second = cycle()->creerContratDepuisMatching($matching);

    expect($second->id)->toBe($premier->id)
        ->and($second->wasRecentlyCreated)->toBeFalse()
        ->and(Contract::query()->count())->toBe(1);
});

it('bloque en base le double contrat actif pour le même candidat et la même entreprise', function () {
    $matching = matchingAccepte();
    $contract = cycle()->creerContratDepuisMatching($matching);

    expect(fn () => Contract::query()->create([
        'candidate_id' => $contract->candidate_id,
        'company_id' => $contract->company_id,
    ]))->toThrow(ValidationException::class);
});

it('autorise un nouveau contrat après rupture du précédent (même couple)', function () {
    $matching = matchingAccepte();
    $contract = cycle()->creerContratDepuisMatching($matching);

    $contract->forceFill(['statut_contrat' => ContractStatut::Rompu])->save();

    $nouveau = Contract::query()->create([
        'candidate_id' => $contract->candidate_id,
        'company_id' => $contract->company_id,
    ]);

    expect($nouveau->exists)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Contrat signé → Dossier OPCO
|--------------------------------------------------------------------------
*/

it('refuse un dossier OPCO tant que le contrat n\'est pas signé par les trois parties', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnvoyeSignature,
        'statut_signature' => ContractSignatureStatut::NonSigne,
    ]);

    expect(fn () => OpcoFile::query()->create(['contract_id' => $contract->id]))
        ->toThrow(ValidationException::class, CycleApprenant::MSG_CONTRAT_NON_SIGNE);
});

it('ouvre le dossier OPCO automatiquement à la signature du contrat (préparation)', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnvoyeSignature,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    $contract->transitionTo(ContractStatut::Signe);

    expect($contract->opcoFile)->not->toBeNull()
        ->and($contract->opcoFile->statut)->toBe(OpcoStatut::APreparer);
});

/*
|--------------------------------------------------------------------------
| Timeline du parcours
|--------------------------------------------------------------------------
*/

it('résume le parcours dans la timeline : étapes, états et étape courante', function () {
    $matching = matchingAccepte();
    $candidat = $matching->candidate;

    $etapes = collect(cycle()->etapes($candidat))->keyBy('cle');

    expect($etapes)->toHaveCount(6)
        ->and($etapes['candidat']['etat'])->toBe(CycleApprenant::ETAT_TERMINEE)
        ->and($etapes['matching']['etat'])->toBe(CycleApprenant::ETAT_TERMINEE)
        ->and($etapes['contrat']['etat'])->toBe(CycleApprenant::ETAT_NON_DEMARREE)
        ->and($etapes['admission']['etat'])->toBe(CycleApprenant::ETAT_NON_DEMARREE);

    // Après création + signature du contrat et dépôt OPCO : admission en cours.
    $contract = cycle()->creerContratDepuisMatching($matching);
    $contract->forceFill([
        'statut_contrat' => ContractStatut::Signe,
        'statut_signature' => ContractSignatureStatut::Signe,
    ])->save();
    $contract->ouvrirDossierOpco();
    $contract->opcoFile->transitionTo(OpcoStatut::PretDepot);

    $etapes = collect(cycle()->etapes($candidat->fresh()))->keyBy('cle');

    expect($etapes['contrat']['etat'])->toBe(CycleApprenant::ETAT_TERMINEE)
        ->and($etapes['opco']['etat'])->toBe(CycleApprenant::ETAT_TERMINEE)
        ->and($etapes['admission']['etat'])->toBe(CycleApprenant::ETAT_EN_COURS)
        ->and(cycle()->etapeCourante($candidat->fresh())['cle'])->toBe('admission');
});

it('marque le parcours bloqué pour un candidat refusé', function () {
    $candidat = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);

    $etapes = collect(cycle()->etapes($candidat))->keyBy('cle');

    expect($etapes['candidat']['etat'])->toBe(CycleApprenant::ETAT_BLOQUEE)
        ->and($etapes['matching']['etat'])->toBe(CycleApprenant::ETAT_BLOQUEE);
});
