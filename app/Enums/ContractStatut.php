<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Statut d'un contrat d'apprentissage — volontairement resserré à 4 états de
 * travail, plus « Rompu » réservé à la rupture d'apprenti (section Ruptures) :
 *
 *  - « En cours » : contrat créé (souvent auto depuis le Matching), en cours
 *    de complétion / de préparation, pas encore signé ;
 *  - « Manque la signature » : CERFA et convention complets, en attente de la
 *    signature des trois parties ;
 *  - « Complet » : documents complets ET signés → le dossier OPCO s'ouvre
 *    automatiquement ;
 *  - « À corriger » : le dossier est revenu de l'OPCO avec un refus ; il
 *    repasse ici pour correction avant nouvelle transmission ;
 *  - « Rompu » : rupture anticipée du contrat d'apprentissage (piloté par la
 *    section Ruptures, jamais saisi à la main).
 */
enum ContractStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case EnCours = 'en_cours';
    case ManqueSignature = 'manque_signature';
    case Complet = 'complet';
    case ACorriger = 'a_corriger';
    case Rompu = 'rompu';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::ManqueSignature => 'Manque la signature',
            self::Complet => 'Complet',
            self::ACorriger => 'À corriger',
            self::Rompu => 'Rompu',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnCours => 'gray',
            self::ManqueSignature => 'info',
            self::Complet => 'success',
            self::ACorriger => 'warning',
            self::Rompu => 'danger',
        };
    }

    /**
     * Contrats signés (apprentissage engagé) : « Complet » et « À corriger »
     * (ce dernier a été signé puis renvoyé par l'OPCO). Sert aux statistiques
     * « contrats signés / actifs ».
     */
    public static function signes(): array
    {
        return [self::Complet->value, self::ACorriger->value];
    }

    public function transitions(): array
    {
        return match ($this) {
            self::EnCours => [self::ManqueSignature, self::Rompu],
            self::ManqueSignature => [self::Complet, self::EnCours, self::Rompu],
            self::Complet => [self::ACorriger, self::Rompu],
            self::ACorriger => [self::Complet, self::ManqueSignature, self::Rompu],
            self::Rompu => [],
        };
    }
}
