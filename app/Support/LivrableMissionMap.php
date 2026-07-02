<?php

namespace App\Support;

/**
 * Correspondance livrable LivretRS → missions CFA officielles (numéros L6231-2).
 *
 * ⚠️ Ce mapping est une SUGGESTION interne d'aide à la saisie, PAS une vérité
 * réglementaire : à l'import, les missions proposées restent modifiables par
 * l'équipe. Il est volontairement CONSERVATEUR — seuls les rattachements
 * défendables en audit sont proposés. Les pièces qui relèvent de Qualiopi ou
 * d'obligations légales hors des 14 missions (RGPD, réclamations, synthèse
 * interne…) ne sont pas taguées automatiquement (tableau vide).
 *
 * Les codes proviennent de « core/livrables.yaml » de LivretRS ; les numéros
 * sont ceux de l'article L6231-2 (voir CfaMissionSeeder).
 */
class LivrableMissionMap
{
    /** code livrable => liste des numéros de missions L6231-2 suggérés. */
    public const MAP = [
        // 00_REGLEMENTATION
        'livret_accueil' => [1, 4],
        'reglement_interieur' => [4],
        'charte_informatique' => [4],
        'notice_rgpd' => [],                    // RGPD : hors 14 missions
        'notice_engagements_cfa' => [],
        'procedure_disciplinaire' => [4],
        'procedure_mediation' => [6],
        'procedure_reclamation' => [],          // réclamations : critère Qualiopi 7
        'procedure_handicap' => [1],
        'droits_devoirs_apprenti' => [4],
        'document_aides_apprenti' => [14],
        'document_rupture' => [5, 13],
        'attestation_remise_documents' => [4],
        // 01_PEDAGOGIE
        'programme_formation' => [3],
        'planning_formation' => [3],
        'modalites_evaluation' => [12],
        'fiche_positionnement' => [12],
        // 02_APPRENTI
        'livret_apprentissage' => [3, 12],
        'livret_maitre_apprentissage' => [3],
        'fiche_suivi_apprenti' => [6],
        // 05_SYNTHESE
        'synthese_dossier' => [],
    ];

    /** Alias de nom de fichier connus (LivretRS) => code livrable canonique. */
    public const ALIASES = [
        'livretma' => 'livret_maitre_apprentissage',
        'livretmaitre' => 'livret_maitre_apprentissage',
        'livretmaitreapprentissage' => 'livret_maitre_apprentissage',
    ];

    /**
     * Détecte le code livrable à partir d'un nom de fichier LivretRS
     * (« LivretAccueil_DUPONT_jean.pdf » → « livret_accueil »).
     * Insensible à la casse et aux séparateurs. Null si non reconnu.
     */
    public static function detect(string $filename): ?string
    {
        $norm = self::normalize($filename);

        if ($norm === '') {
            return null;
        }

        // Codes les plus longs d'abord (évite qu'un code préfixe d'un autre gagne).
        $codes = array_keys(self::MAP);
        usort($codes, fn ($a, $b) => strlen(self::normalize($b)) <=> strlen(self::normalize($a)));

        foreach ($codes as $code) {
            if (str_starts_with($norm, self::normalize($code))) {
                return $code;
            }
        }

        foreach (self::ALIASES as $alias => $code) {
            if (str_starts_with($norm, self::normalize($alias))) {
                return $code;
            }
        }

        return null;
    }

    /** Numéros de missions L6231-2 suggérés pour un code livrable. */
    public static function missionNumeros(?string $code): array
    {
        return $code === null ? [] : (self::MAP[$code] ?? []);
    }

    /** Réduit une chaîne à ses lettres/chiffres en minuscules (sans extension). */
    private static function normalize(string $value): string
    {
        $value = preg_replace('/\.[a-z0-9]+$/i', '', $value); // retire l'extension
        $value = strtolower((string) $value);

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }
}
