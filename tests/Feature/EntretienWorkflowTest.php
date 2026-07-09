<?php

use App\Enums\CandidateStatut;
use App\Enums\EntretienStatut;
use App\Enums\MatchingStatut;
use App\Models\Candidate;
use App\Models\Entretien;
use App\Models\Matching;
use App\Models\User;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use App\StateMachine\InvalidTransitionException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function candidatAPlanifier(): Candidate
{
    return Candidate::factory()->create(['statut' => CandidateStatut::EntretienAPlanifier]);
}

function cycleEntretiens(): CycleApprenant
{
    return app(CycleApprenant::class);
}

function besoinOuvertPourEntretiens(): \App\Models\Need
{
    return \App\Models\Need::factory()->create(['statut' => \App\Enums\NeedStatut::ProfilsEnvoyes]);
}

/*
|--------------------------------------------------------------------------
| Création candidat & section Entretiens
|--------------------------------------------------------------------------
*/

it('ne crée aucun matching tant que le candidat n\'est pas accepté', function () {
    $candidat = candidatAPlanifier();

    expect(Matching::query()->where('candidate_id', $candidat->id)->exists())->toBeFalse();
});

it('refuse de planifier un entretien sans créneau complet (date + heures)', function () {
    $entretien = Entretien::factory()->create();

    expect(fn () => $entretien->transitionTo(EntretienStatut::Planifie))
        ->toThrow(InvalidTransitionException::class, CycleApprenant::MSG_ENTRETIEN_INCOMPLET);

    // Même blocage par écriture directe (backend solide).
    expect(fn () => $entretien->forceFill(['statut' => EntretienStatut::Planifie])->save())
        ->toThrow(ValidationException::class);
});

it('refuse une heure de fin antérieure à l\'heure de début', function () {
    expect(fn () => Entretien::factory()->create([
        'date_entretien' => now()->addDay()->toDateString(),
        'heure_debut' => '11:00',
        'heure_fin' => '10:00',
    ]))->toThrow(ValidationException::class);
});

it('passe automatiquement le candidat à « Entretien prévu » quand l\'entretien est planifié', function () {
    $candidat = candidatAPlanifier();

    $entretien = Entretien::factory()->create(['candidate_id' => $candidat->id]);
    $entretien->forceFill([
        'date_entretien' => now()->addDays(2)->toDateString(),
        'heure_debut' => '09:00',
        'heure_fin' => '10:00',
    ])->save();
    $entretien->transitionTo(EntretienStatut::Planifie);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienPrevu);
});

it('repasse le candidat à « Entretien à planifier » quand l\'entretien est annulé', function () {
    $candidat = candidatAPlanifier();
    $entretien = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienPrevu);

    $entretien->transitionTo(EntretienStatut::Annule);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienAPlanifier);
});

it('interdit un second entretien actif pour le même candidat (anti-doublon)', function () {
    $candidat = candidatAPlanifier();
    Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);

    // Un seul entretien actif à la fois : on reprogramme l'existant.
    expect(fn () => Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]))
        ->toThrow(ValidationException::class, CycleApprenant::MSG_ENTRETIEN_EN_COURS);

    expect($candidat->entretiens()->count())->toBe(1);
});

it('autorise un nouvel entretien après clôture du précédent (annulé)', function () {
    $candidat = candidatAPlanifier();
    $premier = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);
    $premier->transitionTo(EntretienStatut::Annule);

    // L'ancien n'est plus actif : on peut en reposer un.
    $second = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);

    expect($second->exists)->toBeTrue()
        ->and($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienPrevu);
});

it('repasse le candidat à « Entretien à planifier » quand il est marqué absent', function () {
    $candidat = candidatAPlanifier();
    $entretien = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);

    $entretien->transitionTo(EntretienStatut::Absent);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienAPlanifier);
});

/*
|--------------------------------------------------------------------------
| Statut candidat piloté par les entretiens
|--------------------------------------------------------------------------
*/

it('interdit « Entretien prévu » sans entretien réellement planifié', function () {
    $candidat = candidatAPlanifier();

    expect($candidat->transitionBlockReason(CandidateStatut::EntretienPrevu))
        ->toBe(CycleApprenant::MSG_ENTRETIEN_NON_PLANIFIE);
});

it('interdit d\'accepter un candidat sans entretien réalisé', function () {
    $candidat = candidatAPlanifier();

    expect(fn () => $candidat->transitionTo(CandidateStatut::Accepte))
        ->toThrow(InvalidTransitionException::class, CycleApprenant::MSG_ACCEPTATION_SANS_ENTRETIEN);
});

it('laisse l\'administrateur accepter exceptionnellement sans entretien réalisé', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['Administrateur']);
    $this->actingAs($admin);

    $candidat = candidatAPlanifier();
    $candidat->transitionTo(CandidateStatut::Accepte);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('interdit tout retour aux statuts d\'entretien après une décision finale', function () {
    $candidat = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);

    expect(fn () => $candidat->transitionTo(CandidateStatut::EntretienAPlanifier))
        ->toThrow(InvalidTransitionException::class, CycleApprenant::MSG_RETOUR_A_PLANIFIER);

    expect(fn () => $candidat->forceFill(['statut' => CandidateStatut::EntretienAPlanifier])->save())
        ->toThrow(ValidationException::class);
});

it('passe le candidat à « Entretien réalisé » quand l\'entretien est réalisé', function () {
    $candidat = candidatAPlanifier();
    $entretien = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienPrevu);

    $entretien->transitionTo(EntretienStatut::Realise);

    // L'entretien a eu lieu : décision (Accepté / Refusé) en attente.
    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienRealise);
});

it('n\'envoie pas au Matching au statut « Entretien réalisé » (décision en attente)', function () {
    $candidat = candidatAPlanifier();
    $entretien = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);
    $entretien->transitionTo(EntretienStatut::Realise);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienRealise)
        ->and(Matching::query()->where('candidate_id', $candidat->id)->exists())->toBeFalse();
});

it('ne propose depuis « Entretien réalisé » que les décisions Accepté ou Refusé', function () {
    $candidat = candidatAPlanifier();
    $entretien = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);
    $entretien->transitionTo(EntretienStatut::Realise);

    $transitions = $candidat->fresh()->allowedTransitions();

    expect($transitions)->toEqualCanonicalizing([CandidateStatut::Accepte, CandidateStatut::Refuse]);
});

it('accepte le candidat depuis « Entretien réalisé » et ouvre le Matching', function () {
    $candidat = candidatAPlanifier();
    $entretien = Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);
    $entretien->transitionTo(EntretienStatut::Realise);

    expect($candidat->fresh()->statut)->toBe(CandidateStatut::EntretienRealise);

    $candidat = cycleEntretiens()->deciderApresEntretien($entretien, accepte: true);

    expect($candidat->statut)->toBe(CandidateStatut::Accepte)
        ->and(Matching::query()
            ->where('candidate_id', $candidat->id)
            ->where('statut', MatchingStatut::EnRecherche->value)
            ->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Décision après entretien réalisé → déclencheurs automatiques
|--------------------------------------------------------------------------
*/

it('accepte le candidat après entretien réalisé et ouvre AUTOMATIQUEMENT le Matching', function () {
    $entretien = Entretien::factory()->realise()->create();

    $candidat = cycleEntretiens()->deciderApresEntretien($entretien, accepte: true, compteRendu: 'Avis favorable.');

    $matching = Matching::query()->where('candidate_id', $candidat->id)->first();

    expect($candidat->statut)->toBe(CandidateStatut::Accepte)
        ->and($entretien->fresh()->resultat)->toBe('accepte')
        ->and($matching)->not->toBeNull()
        // Premier statut par défaut de la section Matching, sans entreprise encore.
        ->and($matching->statut)->toBe(MatchingStatut::EnRecherche)
        ->and($matching->need_id)->toBeNull();
});

it('refuse le candidat après entretien réalisé : aucun matching créé', function () {
    $entretien = Entretien::factory()->realise()->create();

    $candidat = cycleEntretiens()->deciderApresEntretien($entretien, accepte: false);

    expect($candidat->statut)->toBe(CandidateStatut::Refuse)
        ->and($entretien->fresh()->resultat)->toBe('refuse')
        ->and(Matching::query()->where('candidate_id', $candidat->id)->exists())->toBeFalse();
});

it('bloque la décision tant que l\'entretien n\'est pas réalisé', function () {
    $entretien = Entretien::factory()->planifie()->create();

    expect(fn () => cycleEntretiens()->deciderApresEntretien($entretien, accepte: true))
        ->toThrow(CycleBloqueException::class);
});

it('ne crée pas de second matching si une recherche est déjà en cours (anti-doublon)', function () {
    $entretien = Entretien::factory()->realise()->create();
    $candidat = cycleEntretiens()->deciderApresEntretien($entretien, accepte: true);

    // Rejoue le déclencheur : idempotent.
    expect(cycleEntretiens()->ouvrirRechercheEntreprise($candidat->fresh()))->toBeNull()
        ->and(Matching::query()->where('candidate_id', $candidat->id)->count())->toBe(1);
});

it('rattache le besoin à la recherche automatique au lieu de créer un doublon', function () {
    $entretien = Entretien::factory()->realise()->create();
    $candidat = cycleEntretiens()->deciderApresEntretien($entretien, accepte: true);

    $besoin = besoinOuvertPourEntretiens();
    $matching = cycleEntretiens()->envoyerVersMatching($candidat->fresh(), $besoin);

    expect($matching->need_id)->toBe($besoin->id)
        ->and(Matching::query()->where('candidate_id', $candidat->id)->count())->toBe(1);
});

it('bloque l\'avancement d\'un matching sans entreprise rattachée', function () {
    $entretien = Entretien::factory()->realise()->create();
    $candidat = cycleEntretiens()->deciderApresEntretien($entretien, accepte: true);

    $matching = Matching::query()->where('candidate_id', $candidat->id)->firstOrFail();
    $matching->forceFill(['cv_envoye' => true])->save();

    expect($matching->transitionBlockReason(MatchingStatut::PropositionEnvoyee))
        ->toBe('Rattachez une entreprise (besoin) avant de faire avancer ce matching.');
});

/*
|--------------------------------------------------------------------------
| Timeline (étape Entretien)
|--------------------------------------------------------------------------
*/

it('affiche l\'étape Entretien dans la timeline du parcours', function () {
    $candidat = candidatAPlanifier();
    Entretien::factory()->planifie()->create(['candidate_id' => $candidat->id]);

    $etapes = collect(cycleEntretiens()->etapes($candidat->fresh()))->keyBy('cle');

    expect($etapes)->toHaveCount(7)
        ->and($etapes['candidat']['etat'])->toBe(CycleApprenant::ETAT_EN_COURS)
        ->and($etapes['entretien']['etat'])->toBe(CycleApprenant::ETAT_EN_COURS)
        ->and($etapes['entretien']['detail'])->toBe('Entretien planifié')
        ->and(cycleEntretiens()->etapeCourante($candidat->fresh())['cle'])->toBe('candidat');
});

