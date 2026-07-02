<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

    case Brouillon = 'brouillon';
    case Emise = 'emise';
    case Payee = 'payee';
    case Annulee = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Emise => 'Émise',
            self::Payee => 'Payée',
            self::Annulee => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Brouillon => 'gray',
            self::Emise => 'warning',
            self::Payee => 'success',
            self::Annulee => 'danger',
        };
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Brouillon => [self::Emise, self::Annulee],
            self::Emise => [self::Payee, self::Annulee],
            self::Payee => [],
            self::Annulee => [],
        };
    }

    /** Une facture émise engage le CFA (numérotée, comptabilisée). */
    public function estEmise(): bool
    {
        return in_array($this, [self::Emise, self::Payee], true);
    }
}
