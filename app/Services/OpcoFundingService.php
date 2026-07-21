<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\NpecReferentiel;

/**
 * Financement OPCO d'un contrat d'apprentissage. Centralise la détection du
 * NPEC (Niveau de Prise en Charge, référentiel France Compétences) à partir de
 * la certification (RNCP) et de la branche (IDCC), ainsi que les calculs
 * dérivés (NPEC journalier, engagement total). Aucune valeur n'est inventée :
 * si le référentiel ne contient pas la certification, la méthode renvoie null
 * et la saisie manuelle prend le relais.
 */
class OpcoFundingService
{
    /**
     * NPEC de référence pour un couple (IDCC, RNCP). On privilégie la valeur
     * spécifique à la branche (RNCP + IDCC) ; à défaut, la valeur nationale du
     * RNCP (IDCC nul). Null si la certification est absente du référentiel.
     */
    public function findNpecByIdccAndRncp(?string $idcc, ?string $rncp): ?NpecReferentiel
    {
        $rncp = $this->normaliserRncp($rncp);

        if ($rncp === null) {
            return null;
        }

        $idcc = $this->normaliserIdcc($idcc);

        // 1) Valeur spécifique à la branche, si un IDCC exploitable est fourni.
        if ($idcc !== null) {
            $branche = NpecReferentiel::query()
                ->where('code_rncp', $rncp)
                ->where('code_idcc', $idcc)
                ->first();

            if ($branche !== null) {
                return $branche;
            }
        }

        // 2) Valeur nationale par défaut du RNCP (IDCC nul).
        return NpecReferentiel::query()
            ->where('code_rncp', $rncp)
            ->whereNull('code_idcc')
            ->first();
    }

    /** NPEC journalier = NPEC annuel ÷ 365 (arrondi au centime). */
    public function calculateDailyNpec(?float $npecAnnuel): ?float
    {
        if ($npecAnnuel === null || $npecAnnuel <= 0) {
            return null;
        }

        return round($npecAnnuel / 365, 2);
    }

    /**
     * Engagement OPCO total = NPEC journalier × nombre de jours du contrat.
     * Le nombre de jours prime s'il est fourni ; sinon calculé depuis les dates.
     */
    public function calculateTotalOpcoFunding(?float $npecAnnuel, ?int $nombreJours, Contract $contract = null): ?float
    {
        $journalier = $this->calculateDailyNpec($npecAnnuel);

        if ($journalier === null) {
            return null;
        }

        $jours = $nombreJours ?? ($contract !== null ? $this->joursContrat($contract) : null);

        if ($jours === null || $jours <= 0) {
            return null;
        }

        return round($journalier * $jours, 2);
    }

    /**
     * Données manquantes empêchant la détection automatique du NPEC.
     *
     * @return list<string> libellés lisibles des données manquantes
     */
    public function getMissingFundingData(Contract $contract): array
    {
        $manquants = [];

        if ($this->normaliserRncp($contract->code_rncp ?? $contract->formation?->code_rncp) === null) {
            $manquants[] = 'le code RNCP de la formation';
        }

        return $manquants;
    }

    /** Nombre de jours calendaires du contrat (bornes incluses), ou null. */
    private function joursContrat(Contract $contract): ?int
    {
        $debut = $contract->dateDebutEffective();
        $fin = $contract->dateFinEffective();

        if ($debut === null || $fin === null || $fin->lt($debut)) {
            return null;
        }

        return $debut->diffInDays($fin) + 1;
    }

    /** RNCP normalisé « RNCP12345 » → « 12345 » (chiffres seuls), ou null. */
    private function normaliserRncp(?string $rncp): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $rncp);

        return $chiffres !== '' ? $chiffres : null;
    }

    /** IDCC normalisé (chiffres) ; les codes « sans convention » (9998/9999) sont ignorés. */
    private function normaliserIdcc(?string $idcc): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $idcc);

        if ($chiffres === '' || in_array($chiffres, ['9998', '9999'], true)) {
            return null;
        }

        return $chiffres;
    }
}
