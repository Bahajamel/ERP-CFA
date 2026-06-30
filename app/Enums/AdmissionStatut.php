<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdmissionStatut: string implements HasLabel, HasColor
{
    case AVerifier = 'a_verifier';
    case Incomplet = 'incomplet';
    case NonConforme = 'non_conforme';
    case Valide = 'valide';
    case Refuse = 'refuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::AVerifier => 'À vérifier',
            self::Incomplet => 'Incomplet',
            self::NonConforme => 'Non conforme',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AVerifier => 'info',
            self::Incomplet => 'gray',
            self::NonConforme => 'warning',
            self::Valide => 'success',
            self::Refuse => 'danger',
        };
    }
}
