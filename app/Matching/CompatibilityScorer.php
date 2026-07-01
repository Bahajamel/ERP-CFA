<?php

namespace App\Matching;

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\Models\Need;

/**
 * Calcule un score de compatibilité candidat ↔ besoin par règles déterministes
 * (explicables, testables — pas d'IA en V1). Cf. docs/architecture/
 * excellence-commerciale-crm-matching.md (F2).
 */
class CompatibilityScorer
{
    /** Poids de chaque critère (score max = 100). */
    public const POIDS = [
        'formation' => 50,
        'disponibilite' => 25,
        'mobilite' => 15,
        'niveau' => 10,
    ];

    /**
     * Détail des critères remplis (pour l'affichage et l'explicabilité).
     *
     * @return array<string, bool>
     */
    public function criteres(Candidate $candidate, Need $need): array
    {
        return [
            'formation' => $candidate->formation_visee_id !== null
                && $candidate->formation_visee_id === $need->formation_id,
            'disponibilite' => $candidate->statut === CandidateStatut::EnRechercheEntreprise,
            'mobilite' => filled($need->localisation) && filled($candidate->mobilite)
                && str_contains(mb_strtolower($candidate->mobilite), mb_strtolower($need->localisation)),
            'niveau' => filled($candidate->niveau_actuel),
        ];
    }

    public function score(Candidate $candidate, Need $need): int
    {
        $score = 0;

        foreach ($this->criteres($candidate, $need) as $critere => $rempli) {
            if ($rempli) {
                $score += self::POIDS[$critere];
            }
        }

        return $score;
    }

    /** Libellé court des critères remplis, ex. « Formation · Dispo ». */
    public function explication(Candidate $candidate, Need $need): string
    {
        $labels = [
            'formation' => 'Formation',
            'disponibilite' => 'Dispo',
            'mobilite' => 'Mobilité',
            'niveau' => 'Niveau',
        ];

        $remplis = array_keys(array_filter($this->criteres($candidate, $need)));

        return implode(' · ', array_map(fn (string $c) => $labels[$c], $remplis)) ?: '—';
    }
}
