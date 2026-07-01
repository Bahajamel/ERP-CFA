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
    Matching::factory()->for($need)->create(['statut' => MatchingStatut::Propose]); // ne compte pas

    expect($need->postesRestants())->toBe(2);
});

it('ne renvoie jamais un nombre de postes restants négatif', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes, 'nb_postes' => 1]);

    Matching::factory()->count(2)->for($need)->create(['statut' => MatchingStatut::Accepte]);

    expect($need->postesRestants())->toBe(0);
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
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::Propose]);

    expect(fn () => $matching->update(['statut' => MatchingStatut::Accepte]))
        ->toThrow(ValidationException::class);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::Propose);
});

it('la garde de transition bloque « Accepté » quand le besoin est clôturé', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Archive]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::AttenteRetour]);

    expect($matching->guardTransition(MatchingStatut::AttenteRetour, MatchingStatut::Accepte))
        ->not->toBeNull();
});

it('autorise « Accepté » tant que le besoin est ouvert', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::EntretienPrevu]);
    $matching = Matching::factory()->for($need)->create(['statut' => MatchingStatut::AttenteRetour]);

    $matching->update(['statut' => MatchingStatut::Accepte]);

    expect($matching->fresh()->statut)->toBe(MatchingStatut::Accepte);
});
