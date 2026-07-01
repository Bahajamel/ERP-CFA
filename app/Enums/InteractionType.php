<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Canal d'une interaction commerciale avec une entreprise (P0-03-6) : sert à
 * tracer l'historique des échanges (timeline) du suivi relation client.
 */
enum InteractionType: string implements HasColor, HasIcon, HasLabel
{
    case Appel = 'appel';
    case Email = 'email';
    case Rdv = 'rdv';
    case Visite = 'visite';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Appel => 'Appel',
            self::Email => 'E-mail',
            self::Rdv => 'Rendez-vous',
            self::Visite => 'Visite',
            self::Autre => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Appel => 'info',
            self::Email => 'gray',
            self::Rdv => 'success',
            self::Visite => 'warning',
            self::Autre => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Appel => 'heroicon-o-phone',
            self::Email => 'heroicon-o-envelope',
            self::Rdv => 'heroicon-o-calendar-days',
            self::Visite => 'heroicon-o-building-office',
            self::Autre => 'heroicon-o-chat-bubble-left-ellipsis',
        };
    }
}
