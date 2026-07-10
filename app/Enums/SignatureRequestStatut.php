<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Cycle de vie d'une demande de signature électronique multi-parties (EPIC-08). */
enum SignatureRequestStatut: string implements HasLabel, HasColor
{
    case Brouillon = 'brouillon';
    case Envoyee = 'envoyee';
    case PartiellementSignee = 'partiellement_signee';
    case Signee = 'signee';
    case Refusee = 'refusee';
    case Expiree = 'expiree';

    public function getLabel(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Envoyee => 'Envoyée aux signataires',
            self::PartiellementSignee => 'Partiellement signée',
            self::Signee => 'Signée par toutes les parties',
            self::Refusee => 'Refusée',
            self::Expiree => 'Expirée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Brouillon => 'gray',
            self::Envoyee => 'info',
            self::PartiellementSignee => 'warning',
            self::Signee => 'success',
            self::Refusee => 'danger',
            self::Expiree => 'danger',
        };
    }

    /** En cours (envoyée mais pas encore aboutie). */
    public static function enCours(): array
    {
        return [self::Envoyee->value, self::PartiellementSignee->value];
    }
}
