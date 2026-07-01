<?php

namespace App\Support;

use App\Enums\RiskLevel;

/**
 * Résultat de l'évaluation du risque de rupture d'un contrat : score plafonné
 * à 100, niveau dérivé, et la liste des facteurs qui l'expliquent.
 */
readonly class RiskAssessment
{
    /** @param list<RiskFactor> $factors */
    public function __construct(
        public int $score,
        public RiskLevel $level,
        public array $factors,
    ) {}

    /** Construit l'évaluation à partir des facteurs détectés (score plafonné). */
    public static function fromFactors(array $factors): self
    {
        $score = min(100, array_sum(array_map(fn (RiskFactor $f) => $f->points, $factors)));

        return new self($score, RiskLevel::fromScore($score), array_values($factors));
    }

    /** @return list<array{code: string, label: string, points: int}> */
    public function factorsToArray(): array
    {
        return array_map(fn (RiskFactor $f) => $f->toArray(), $this->factors);
    }

    /** Libellés des facteurs, pour un affichage condensé. */
    public function labels(): array
    {
        return array_map(fn (RiskFactor $f) => $f->label, $this->factors);
    }
}
