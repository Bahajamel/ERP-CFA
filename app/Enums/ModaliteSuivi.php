<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Modalité de suivi de la formation. Sert au contrôle « majorité à distance »
 * (minoration possible de la prise en charge OPCO, décret du 1er juillet 2025).
 */
enum ModaliteSuivi: string implements HasLabel
{
    case Presentiel = 'presentiel';
    case Distance = 'distance';
    case Mixte = 'mixte';

    public function getLabel(): string
    {
        return match ($this) {
            self::Presentiel => 'Présentiel',
            self::Distance => 'À distance',
            self::Mixte => 'Mixte (hybride)',
        };
    }
}
