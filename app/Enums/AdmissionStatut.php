<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Admission officielle de l'apprenant — dernière étape du cycle d'entrée
 * (et non une pré-admission). Une admission n'existe qu'après : candidat
 * accepté, entreprise trouvée, contrat signé par les trois parties et
 * dossier OPCO créé ou transmis pour validation. Elle démarre toujours
 * « À vérifier » ; « Rupture » alimente le module Rupture (livrables).
 */
enum AdmissionStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case AVerifier = 'a_verifier';
    case Valide = 'valide';
    case Rupture = 'rupture';

    public function getLabel(): string
    {
        return match ($this) {
            self::AVerifier => 'À vérifier',
            self::Valide => 'Validé',
            self::Rupture => 'Rupture',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AVerifier => 'info',
            self::Valide => 'success',
            self::Rupture => 'danger',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::AVerifier => [self::Valide, self::Rupture],
            self::Valide => [self::Rupture],
            self::Rupture => [],
        };
    }
}
