<?php

use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/** Un besoin ouvert + un candidat, prêts à être appariés. */
function besoinOuvert(): Need
{
    return Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes, 'nb_postes' => 1]);
}

// ── Création & doublon ────────────────────────────────────────────────────────

it('crée un matching « En recherche » entre un candidat accepté et un besoin', function () {
    $need = besoinOuvert();
    $candidate = Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte]);

    $matching = Matching::create([
        'need_id' => $need->id,
        'candidate_id' => $candidate->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    expect($matching->exists)->toBeTrue()
        ->and($matching->statut)->toBe(MatchingStatut::EnRecherche);
});

it('empêche de proposer deux fois le même candidat sur le même besoin', function () {
    $need = besoinOuvert();
    $candidate = Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte]);
    Matching::create(['need_id' => $need->id, 'candidate_id' => $candidate->id, 'statut' => MatchingStatut::EnRecherche]);

    expect(fn () => Matching::create([
        'need_id' => $need->id,
        'candidate_id' => $candidate->id,
        'statut' => MatchingStatut::EnRecherche,
    ]))->toThrow(QueryException::class);
});

// ── CV envoyé ─────────────────────────────────────────────────────────────────

it('passe en « Proposition envoyée » lorsque le CV est marqué envoyé', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
        'cv_envoye' => false,
    ]);

    $matching->update(['cv_envoye' => true, 'statut' => MatchingStatut::PropositionEnvoyee]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::PropositionEnvoyee);
});

it('empêche « Proposition envoyée » si le CV n\'est pas marqué envoyé', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
        'cv_envoye' => false,
    ]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::PropositionEnvoyee]))
        ->toThrow(ValidationException::class);
    expect($matching->fresh()->statut)->toBe(MatchingStatut::EnRecherche);
});

// ── Entretien prévu ───────────────────────────────────────────────────────────

it('passe en « Entretien entreprise » avec une date d\'entretien', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    $matching->update(['date_entretien' => now()->addDays(3), 'statut' => MatchingStatut::EntretienEntreprise]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::EntretienEntreprise);
});

it('empêche « Entretien entreprise » sans date d\'entretien', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
        'date_entretien' => null,
    ]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::EntretienEntreprise]))
        ->toThrow(ValidationException::class);
});

// ── Refus ─────────────────────────────────────────────────────────────────────

it('refuse un matching avec un motif de refus', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    $matching->update(['refusal_reason' => 'Profil non retenu', 'statut' => MatchingStatut::Refuse]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::Refuse);
});

it('empêche un refus sans motif ni commentaire', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::Refuse]))
        ->toThrow(ValidationException::class);
});

// ── Acceptation ───────────────────────────────────────────────────────────────

it('accepte un matching sur un besoin ouvert', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::PropositionEnvoyee,
    ]);

    $matching->update(['statut' => MatchingStatut::Accepte]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::Accepte);
});

it('empêche l\'acceptation si le besoin est clôturé', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Pourvu]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::EnRecherche]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::Accepte]))
        ->toThrow(ValidationException::class);
});

// ── Journalisation ────────────────────────────────────────────────────────────

it('journalise les évolutions du matching (activity log)', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
        'cv_envoye' => true,
    ]);

    $matching->update(['statut' => MatchingStatut::PropositionEnvoyee]);

    expect(Activity::query()->where('log_name', 'matching')->exists())->toBeTrue();
});

// ── Cycle apprenant : seuls les candidats acceptés entrent au Matching ───────

it('refuse la création d\'un matching pour un candidat non accepté', function () {
    $need = besoinOuvert();
    $candidat = Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::EntretienPrevu]);

    expect(fn () => Matching::create([
        'need_id' => $need->id,
        'candidate_id' => $candidat->id,
        'statut' => MatchingStatut::EnRecherche,
    ]))->toThrow(ValidationException::class);
});

it('refuse la création d\'un matching pour un candidat refusé', function () {
    $need = besoinOuvert();
    $candidat = Candidate::factory()->create(['statut' => \App\Enums\CandidateStatut::Refuse]);

    expect(fn () => Matching::create([
        'need_id' => $need->id,
        'candidate_id' => $candidat->id,
        'statut' => MatchingStatut::EnRecherche,
    ]))->toThrow(ValidationException::class);
});
