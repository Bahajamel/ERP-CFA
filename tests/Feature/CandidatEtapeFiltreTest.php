<?php

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Filtre « Progression » + colonne du même nom.
 *
 * Le filtre est le miroir SQL de progressionEtapes() : les deux doivent rester
 * d'accord. Un filtre qui contredirait les points affichés serait pire que pas
 * de filtre du tout — d'où le test de cohérence en fin de fichier.
 */

/** Étape courante telle que l'affiche la colonne Progression. */
function etapeAffichee(Candidate $c): string
{
    $etapes = collect($c->fresh()->progressionEtapes());

    if ($refuse = $etapes->firstWhere('etat', 'refuse')) {
        return 'refuse';
    }

    return $etapes->firstWhere('etat', 'current')['cle'] ?? 'termine';
}

/** Candidat accepté, avec un matching sur une offre. */
function candidatEnMatching(): Candidate
{
    $c = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);

    Matching::create([
        'need_id' => Need::factory()->create()->id,
        'candidate_id' => $c->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    return $c;
}

it('affiche un candidat refusé comme refusé, pas comme en attente d’entretien', function () {
    $c = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);

    $etapes = collect($c->progressionEtapes());

    // Le refus doit apparaître à l'étape « Accepté » (la décision), et la phase
    // d'entretien est close : un refus la termine autant qu'une acceptation.
    expect($etapes->firstWhere('cle', 'entretien')['etat'])->toBe('done')
        ->and($etapes->firstWhere('cle', 'decision')['etat'])->toBe('refuse')
        ->and($etapes->pluck('etat'))->not->toContain('current');
});

it('range chaque candidat à l’étape attendue', function () {
    $aEntretien = Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);
    $enDecision = Candidate::factory()->create(['statut' => CandidateStatut::EntretienRealise]);
    $refuse = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);
    $chercheEntreprise = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
    $enMatching = candidatEnMatching();

    expect(Candidate::query()->aEtape('entretien')->pluck('id'))->toContain($aEntretien->id)
        ->and(Candidate::query()->aEtape('decision')->pluck('id'))->toContain($enDecision->id)
        ->and(Candidate::query()->aEtape('refuse')->pluck('id'))->toContain($refuse->id)
        // Accepté sans aucune piste entreprise : le gros du travail commercial.
        ->and(Candidate::query()->aEtape('matching')->pluck('id'))
        ->toContain($chercheEntreprise->id)
        ->and(Candidate::query()->aEtape('matching')->pluck('id'))
        ->not->toContain($enMatching->id)
        // En piste, mais admission pas encore validée.
        ->and(Candidate::query()->aEtape('admission')->pluck('id'))->toContain($enMatching->id);
});

it('ne considère le parcours complet qu’une fois l’admission validée', function () {
    $c = candidatEnMatching();

    expect(Candidate::query()->aEtape('termine')->pluck('id'))->not->toContain($c->id)
        ->and(Candidate::query()->aEtape('admission')->pluck('id'))->toContain($c->id);

    $admission = new Admission(['statut' => AdmissionStatut::Valide->value]);
    $admission->candidate_id = $c->id;
    $admission->organisation_id = $c->organisation_id; // saveQuietly ne rattache pas au CFA
    $admission->saveQuietly();

    expect(Candidate::query()->aEtape('termine')->pluck('id'))->toContain($c->id)
        ->and(Candidate::query()->aEtape('admission')->pluck('id'))->not->toContain($c->id);
});

it('accorde le filtre avec la colonne Progression, pour chaque étape', function () {
    // Le garde-fou qui compte : si l'un des deux évolue sans l'autre, ça casse ici.
    $admissionValidee = candidatEnMatching();
    $a = new Admission(['statut' => AdmissionStatut::Valide->value]);
    $a->candidate_id = $admissionValidee->id;
    $a->organisation_id = $admissionValidee->organisation_id;
    $a->saveQuietly();

    $cas = [
        'entretien' => Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]),
        'decision' => Candidate::factory()->create(['statut' => CandidateStatut::EntretienRealise]),
        'refuse' => Candidate::factory()->create(['statut' => CandidateStatut::Refuse]),
        'matching' => Candidate::factory()->create(['statut' => CandidateStatut::Accepte]),
        'admission' => candidatEnMatching(),
        'termine' => $admissionValidee,
    ];

    foreach ($cas as $etape => $candidat) {
        expect(etapeAffichee($candidat))->toBe($etape === 'termine' ? 'termine' : $etape);
        expect(Candidate::query()->aEtape($etape)->pluck('id')->all())
            ->toContain($candidat->id);
    }
});
