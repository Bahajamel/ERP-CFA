<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdmissionStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

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

    public function transitions(): array
    {
        return match ($this) {
            self::AVerifier => [self::Incomplet, self::NonConforme, self::Valide, self::Refuse],
            self::Incomplet => [self::AVerifier, self::Refuse],
            self::NonConforme => [self::AVerifier, self::Refuse],
            self::Valide => [],
            self::Refuse => [],
        };
    }
}
