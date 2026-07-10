<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OpcoStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case NonCree = 'non_cree';
    case APreparer = 'a_preparer';
    case PretDepot = 'pret_depot';
    case Depose = 'depose';
    case AttenteRetour = 'attente_retour';
    case Accepte = 'accepte';
    case Rejete = 'rejete';
    case EnCorrection = 'en_correction';
    case Corrige = 'corrige';
    case Cloture = 'cloture';

    public function getLabel(): string
    {
        return match ($this) {
            self::NonCree => 'Non créé',
            self::APreparer => 'À préparer',
            self::PretDepot => 'Prêt au dépôt',
            self::Depose => 'Déposé',
            self::AttenteRetour => 'En attente retour OPCO',
            self::Accepte => 'Accepté',
            self::Rejete => 'Rejeté',
            self::EnCorrection => 'En correction',
            self::Corrige => 'Corrigé',
            self::Cloture => 'Clôturé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NonCree => 'gray',
            self::APreparer => 'gray',
            self::PretDepot => 'info',
            self::Depose => 'info',
            self::AttenteRetour => 'warning',
            self::Accepte => 'success',
            self::Rejete => 'danger',
            self::EnCorrection => 'warning',
            self::Corrige => 'info',
            self::Cloture => 'success',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::NonCree => [self::APreparer],
            self::APreparer => [self::PretDepot],
            self::PretDepot => [self::Depose],
            self::Depose => [self::AttenteRetour],
            self::AttenteRetour => [self::Accepte, self::Rejete],
            self::Rejete => [self::EnCorrection],
            self::EnCorrection => [self::Corrige],
            self::Corrige => [self::Depose],
            self::Accepte => [self::Cloture],
            self::Cloture => [],
        };
    }

    /** Statuts considérés comme « bloqués » pour le tableau de bord direction. */
    public static function bloques(): array
    {
        return [self::Rejete->value, self::EnCorrection->value];
    }
}
