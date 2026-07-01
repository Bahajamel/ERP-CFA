<?php

namespace App\Support;

/**
 * Les 7 critères du Référentiel National Qualité (RNQ / Qualiopi).
 */
class QualiopiCriteres
{
    /** numéro de critère (1-7) => libellé */
    public const LABELS = [
        1 => 'Information du public',
        2 => 'Identification des objectifs et adaptation des prestations',
        3 => 'Adaptation aux publics : accueil, accompagnement, suivi, évaluation',
        4 => 'Adéquation des moyens pédagogiques, techniques et d\'encadrement',
        5 => 'Qualification et développement des compétences des personnels',
        6 => 'Inscription dans son environnement professionnel',
        7 => 'Recueil et prise en compte des appréciations et réclamations',
    ];

    public static function label(int $critere): string
    {
        return self::LABELS[$critere] ?? "Critère {$critere}";
    }
}
