<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Traitement administratif d'un dossier de rupture : à traiter, livrables
 * générés, puis clôturé. (L'accompagnement / reclassement ne fait pas
 * partie du workflow actuel.)
 */
enum RuptureStatut: string implements HasColor, HasLabel, HasStateTransitions
{
    use DefinesTransitions;

    case ATraiter = 'a_traiter';
    case DocumentsGeneres = 'documents_generes';
    case Cloturee = 'cloturee';

    public function getLabel(): string
    {
        return match ($this) {
            self::ATraiter => 'À traiter',
            self::DocumentsGeneres => 'Documents générés',
            self::Cloturee => 'Clôturée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ATraiter => 'warning',
            self::DocumentsGeneres => 'info',
            self::Cloturee => 'gray',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::ATraiter => [self::DocumentsGeneres, self::Cloturee],
            self::DocumentsGeneres => [self::Cloturee],
            self::Cloturee => [],
        };
    }
}
