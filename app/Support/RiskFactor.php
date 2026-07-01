<?php

namespace App\Support;

/**
 * Un facteur de risque de rupture : un signal détecté, son poids et un libellé
 * lisible par l'équipe. Sérialisable pour être stocké et affiché tel quel.
 */
readonly class RiskFactor
{
    public function __construct(
        public string $code,
        public string $label,
        public int $points,
    ) {}

    /** @return array{code: string, label: string, points: int} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'points' => $this->points,
        ];
    }
}
