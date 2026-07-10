<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Rémunération minimale légale de l'apprenti : pourcentage du SMIC selon
 * l'âge et l'année d'exécution du contrat (art. L6222-27 à L6222-29 et
 * D6222-26 à D6222-33 du Code du travail — grille vérifiée le 08/07/2026
 * sur service-public.fr, fiche F2918, à jour au 01/06/2026).
 *
 * Règles encodées :
 * - le passage à une tranche d'âge supérieure prend effet le 1er jour du
 *   mois suivant la date d'anniversaire ;
 * - l'année d'exécution change à chaque date anniversaire du contrat ;
 * - à partir de 21 ans, le salaire minimum conventionnel de branche peut
 *   être plus favorable : seul le plancher SMIC est calculé ici (affiché
 *   comme tel, jamais présenté comme le salaire dû).
 */
class RemunerationApprenti
{
    /**
     * Grille légale : âge strictement inférieur à la borne => [année
     * d'exécution => % du SMIC]. 26 ans et plus : 100 % toutes années.
     */
    private const GRILLE = [
        18 => [1 => 27, 2 => 39, 3 => 55],
        21 => [1 => 43, 2 => 51, 3 => 67],
        26 => [1 => 53, 2 => 61, 3 => 78],
        PHP_INT_MAX => [1 => 100, 2 => 100, 3 => 100],
    ];

    /** Seuils d'âge qui font changer de tranche en cours de contrat. */
    private const SEUILS_AGE = [18, 21, 26];

    /** % du SMIC pour un âge et une année d'exécution donnés. */
    public static function taux(int $age, int $annee): int
    {
        // Au-delà de 3 ans (contrats aménagés), le taux de 3e année sert
        // de plancher ; les majorations spécifiques (RQTH…) ne sont pas
        // calculées automatiquement.
        $annee = max(1, min(3, $annee));

        foreach (self::GRILLE as $borne => $taux) {
            if ($age < $borne) {
                return $taux[$annee];
            }
        }

        return 100;
    }

    /**
     * SMIC mensuel brut (35 h) en vigueur à une date : la valeur datée la
     * plus récente antérieure ou égale (config apprentissage). Avant la
     * première valeur connue, celle-ci sert d'approximation.
     */
    public static function smicMensuelBrut(Carbon $date): float
    {
        $bareme = config('apprentissage.smic_mensuel_brut', []);
        ksort($bareme);

        $montant = (float) reset($bareme);

        foreach ($bareme as $depuis => $valeur) {
            if ($date->gte(Carbon::parse($depuis))) {
                $montant = (float) $valeur;
            }
        }

        return $montant;
    }

    /**
     * Découpe le contrat en périodes de rémunération homogènes (mêmes âge,
     * année d'exécution et taux). Les bornes sont les dates anniversaires
     * du contrat et les prises d'effet des changements de tranche d'âge.
     *
     * @return list<array{du: Carbon, au: Carbon, annee: int, age: int, taux: int, smic: float, montant: float}>
     */
    public static function periodes(mixed $dateNaissance, mixed $dateDebut, mixed $dateFin): array
    {
        $naissance = self::date($dateNaissance);
        $debut = self::date($dateDebut);
        $fin = self::date($dateFin);

        if ($naissance === null || $debut === null || $fin === null
            || $fin->lt($debut) || $naissance->gte($debut)) {
            return [];
        }

        $bornes = [$debut->copy()];

        // Nouvelle année d'exécution à chaque date anniversaire du contrat.
        for ($k = 1; $debut->copy()->addYears($k)->lt($fin); $k++) {
            $bornes[] = $debut->copy()->addYears($k);
        }

        // Changement de tranche d'âge : effet le 1er jour du mois suivant
        // l'anniversaire (startOfMonth avant addMonth pour éviter tout
        // débordement de fin de mois).
        foreach (self::SEUILS_AGE as $seuil) {
            $effet = $naissance->copy()->addYears($seuil)->startOfMonth()->addMonth();

            if ($effet->gt($debut) && $effet->lte($fin)) {
                $bornes[] = $effet;
            }
        }

        $bornes = collect($bornes)
            ->unique(fn (Carbon $d): string => $d->toDateString())
            ->sortBy(fn (Carbon $d): string => $d->toDateString())
            ->values();

        return $bornes->map(function (Carbon $du, int $i) use ($bornes, $fin, $naissance, $debut): array {
            $au = $bornes->has($i + 1) ? $bornes->get($i + 1)->copy()->subDay() : $fin->copy();
            $age = (int) floor($naissance->diffInYears($du));
            $annee = (int) floor($debut->diffInYears($du)) + 1;
            $taux = self::taux($age, $annee);
            $smic = self::smicMensuelBrut($du);

            return [
                'du' => $du,
                'au' => $au,
                'annee' => $annee,
                'age' => $age,
                'taux' => $taux,
                'smic' => $smic,
                'montant' => round($smic * $taux / 100, 2),
            ];
        })->all();
    }

    /**
     * Plancher légal applicable à une date de référence (aujourd'hui par
     * défaut, ramenée dans la fenêtre du contrat) — sert de règle de
     * validation du salaire saisi. Le montant est recalculé avec le SMIC
     * en vigueur à la date de référence effective.
     *
     * @return array{du: Carbon, au: Carbon, annee: int, age: int, taux: int, smic: float, montant: float}|null
     */
    public static function minimum(mixed $dateNaissance, mixed $dateDebut, mixed $dateFin, mixed $reference = null): ?array
    {
        $periodes = self::periodes($dateNaissance, $dateDebut, $dateFin);

        if ($periodes === []) {
            return null;
        }

        $reference = self::date($reference) ?? now()->startOfDay();

        foreach ($periodes as $periode) {
            if ($reference->betweenIncluded($periode['du'], $periode['au'])) {
                return self::recalculer($periode, $reference);
            }
        }

        // Référence hors contrat : première période si le contrat n'a pas
        // commencé, dernière s'il est terminé.
        $periode = $reference->lt($periodes[0]['du']) ? $periodes[0] : end($periodes);

        return self::recalculer($periode, $periode['du']);
    }

    /** Réévalue le montant d'une période avec le SMIC à la date donnée. */
    private static function recalculer(array $periode, Carbon $date): array
    {
        // Date ramenée dans les bornes de la période.
        $date = $date->lt($periode['du']) ? $periode['du'] : ($date->gt($periode['au']) ? $periode['au'] : $date);

        $periode['smic'] = self::smicMensuelBrut($date);
        $periode['montant'] = round($periode['smic'] * $periode['taux'] / 100, 2);

        return $periode;
    }

    /** Analyse tolérante d'une date (null si invalide). */
    private static function date(mixed $valeur): ?Carbon
    {
        if (blank($valeur)) {
            return null;
        }

        try {
            return Carbon::parse($valeur)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
