<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * État de conformité d'un indicateur Qualiopi (RNQ) pour l'organisme.
 */
enum QualiopiStatut: string implements HasColor, HasLabel
{
    case AVerifier = 'a_verifier';
    case Conforme = 'conforme';
    case NonConforme = 'non_conforme';
    case NonApplicable = 'non_applicable';

    public function getLabel(): string
    {
        return match ($this) {
            self::AVerifier => 'À vérifier',
            self::Conforme => 'Conforme',
            self::NonConforme => 'Non conforme',
            self::NonApplicable => 'Non applicable',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AVerifier => 'warning',
            self::Conforme => 'success',
            self::NonConforme => 'danger',
            self::NonApplicable => 'gray',
        };
    }

    /** Statuts pris en compte dans le calcul du taux de conformité (hors N/A). */
    public static function applicables(): array
    {
        return [self::AVerifier->value, self::Conforme->value, self::NonConforme->value];
    }
}
