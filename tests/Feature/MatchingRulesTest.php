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

it('crée un matching « Proposé » entre un candidat et un besoin', function () {
    $need = besoinOuvert();
    $candidate = Candidate::factory()->create();

    $matching = Matching::create([
        'need_id' => $need->id,
        'candidate_id' => $candidate->id,
        'statut' => MatchingStatut::Propose,
    ]);

    expect($matching->exists)->toBeTrue()
        ->and($matching->statut)->toBe(MatchingStatut::Propose);
});

it('empêche de proposer deux fois le même candidat sur le même besoin', function () {
    $need = besoinOuvert();
    $candidate = Candidate::factory()->create();
    Matching::create(['need_id' => $need->id, 'candidate_id' => $candidate->id, 'statut' => MatchingStatut::Propose]);

    expect(fn () => Matching::create([
        'need_id' => $need->id,
        'candidate_id' => $candidate->id,
        'statut' => MatchingStatut::Propose,
    ]))->toThrow(QueryException::class);
});

// ── CV envoyé ─────────────────────────────────────────────────────────────────

it('passe en « CV envoyé » lorsque le CV est marqué envoyé', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
        'cv_envoye' => false,
    ]);

    $matching->update(['cv_envoye' => true, 'statut' => MatchingStatut::CvEnvoye]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::CvEnvoye);
});

it('empêche « CV envoyé » si le CV n\'est pas marqué envoyé', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
        'cv_envoye' => false,
    ]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::CvEnvoye]))
        ->toThrow(ValidationException::class);
    expect($matching->fresh()->statut)->toBe(MatchingStatut::Propose);
});

// ── Entretien prévu ───────────────────────────────────────────────────────────

it('passe en « Entretien prévu » avec une date d\'entretien', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
    ]);

    $matching->update(['date_entretien' => now()->addDays(3), 'statut' => MatchingStatut::EntretienPrevu]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::EntretienPrevu);
});

it('empêche « Entretien prévu » sans date d\'entretien', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
        'date_entretien' => null,
    ]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::EntretienPrevu]))
        ->toThrow(ValidationException::class);
});

// ── Refus ─────────────────────────────────────────────────────────────────────

it('refuse un matching avec un motif de refus', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
    ]);

    $matching->update(['refusal_reason' => 'Profil non retenu', 'statut' => MatchingStatut::RefuseEntreprise]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::RefuseEntreprise);
});

it('empêche un refus sans motif ni commentaire', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
    ]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::RefuseCandidat]))
        ->toThrow(ValidationException::class);
});

// ── Acceptation ───────────────────────────────────────────────────────────────

it('accepte un matching sur un besoin ouvert', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::AttenteRetour,
    ]);

    $matching->update(['statut' => MatchingStatut::Accepte]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::Accepte);
});

it('empêche l\'acceptation si le besoin est clôturé', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Pourvu]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::Propose]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::Accepte]))
        ->toThrow(ValidationException::class);
});

// ── Journalisation ────────────────────────────────────────────────────────────

it('journalise les évolutions du matching (activity log)', function () {
    $matching = Matching::create([
        'need_id' => besoinOuvert()->id,
        'candidate_id' => Candidate::factory()->create()->id,
        'statut' => MatchingStatut::Propose,
        'cv_envoye' => true,
    ]);

    $matching->update(['statut' => MatchingStatut::CvEnvoye]);

    expect(Activity::query()->where('log_name', 'matching')->exists())->toBeTrue();
});
