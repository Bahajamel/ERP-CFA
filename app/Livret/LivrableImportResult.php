<?php

namespace App\Livret;

/**
 * Résultat d'un import de pack de livrables LivretRS : compteurs pour le
 * retour utilisateur (notification) et l'audit.
 */
class LivrableImportResult
{
    /** @param list<string> $ignores noms de fichiers non-PDF ignorés */
    public function __construct(
        public int $importes = 0,
        public int $reconnus = 0,
        public int $missionsRattachees = 0,
        public array $ignores = [],
    ) {}

    public function nonReconnus(): int
    {
        return $this->importes - $this->reconnus;
    }
}
