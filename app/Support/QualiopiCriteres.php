<?php

namespace App\Support;

/**
 * Les 7 critères du Référentiel National Qualité (RNQ / Qualiopi).
 * Libellés fidèles au guide de lecture officiel (V8, 23 novembre 2023).
 */
class QualiopiCriteres
{
    /** numéro de critère (1-7) => libellé */
    public const LABELS = [
        1 => 'Information du public sur les prestations, les délais d\'accès et les résultats obtenus',
        2 => 'Identification des objectifs et adaptation des prestations lors de la conception',
        3 => 'Adaptation aux publics : accueil, accompagnement, suivi et évaluation',
        4 => 'Adéquation des moyens pédagogiques, techniques et d\'encadrement',
        5 => 'Qualification et développement des compétences des personnels',
        6 => 'Inscription et investissement dans son environnement professionnel',
        7 => 'Recueil et prise en compte des appréciations et des réclamations',
    ];

    public static function label(int $critere): string
    {
        return self::LABELS[$critere] ?? "Critère {$critere}";
    }
}
