<?php

use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Matching;
use App\Models\Need;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ── P0-04-4 : postes restants + besoins ouverts ───────────────────────────────

it('calcule les postes restants = demandés moins candidats acceptés', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes, 'nb_postes' => 3]);

    Matching::factory()->for($need)->create(['statut' => MatchingStatut::Accepte]);
    Matching::factory()->for($need)->create(['statut' => MatchingStatut::EnRecherche]); // ne compte pas

    expect($need->postesRestants())->toBe(2);
});

it('ne renvoie jamais un nombre de postes restants négatif', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes, 'nb_postes' => 1]);

    // Cet état est devenu inatteignable par la voie normale : le premier candidat
    // accepté clôt l'offre (Need::synchroniserDepuisMatchings) et l'invariant
    // refuse alors tout accepté supplémentaire. On force donc l'état en base,
    // hors événements, pour éprouver le garde-fou de calcul lui-même.
    Matching::factory()->count(2)->for($need)->make(['statut' => MatchingStatut::Accepte])
        ->each(function (Matching $matching) use ($need): void {
            // saveQuietly() neutralise aussi le rattachement automatique au CFA :
            // sans organisation_id, la ligne serait invisible (cloisonnement).
            $matching->organisation_id = $need->organisation_id;
            $matching->saveQuietly();
        });

    expect($need->postesRestants())->toBe(0)
        ->and($need->refresh()->statut)->toBe(NeedStatut::ProfilsEnvoyes); // aucun événement ⇒ statut inchangé
});

it('le scope ouverts exclut les besoins clôturés', function () {
    Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes]);
    Need::factory()->create(['statut' => NeedStatut::CandidatRetenu]);
    Need::factory()->create(['statut' => NeedStatut::Pourvu]);
    Need::factory()->create(['statut' => NeedStatut::Annule]);
    Need::factory()->create(['statut' => NeedStatut::Archive]);

    expect(Need::query()->ouverts()->count())->toBe(2);
});

// ── P0-05-5 : pas d'« Accepté » sur un besoin clôturé ─────────────────────────

it('empêche d\'accepter un candidat sur un besoin clôturé (enregistrement)', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Pourvu]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::EnRecherche]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::Accepte]))
        ->toThrow(ValidationException::class);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::EnRecherche);
});

it('la garde de transition bloque « Accepté » quand le besoin est clôturé', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Archive]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::PropositionEnvoyee]);

    expect($matching->guardTransition(MatchingStatut::PropositionEnvoyee, MatchingStatut::Accepte))
        ->not->toBeNull();
});

it('autorise « Accepté » tant que le besoin est ouvert', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::EntretienPrevu]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::PropositionEnvoyee]);

    $matching->update(['statut' => MatchingStatut::Accepte]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::Accepte);
});
