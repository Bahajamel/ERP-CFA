<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatut: string implements HasColor, HasLabel
{
    case AFaire = 'a_faire';
    case EnCours = 'en_cours';
    case EnAttente = 'en_attente';
    case Terminee = 'terminee';
    case Annulee = 'annulee';
    case EnRetard = 'en_retard';

    public function getLabel(): string
    {
        return match ($this) {
            self::AFaire => 'À faire',
            self::EnCours => 'En cours',
            self::EnAttente => 'En attente',
            self::Terminee => 'Terminée',
            self::Annulee => 'Annulée',
            self::EnRetard => 'En retard',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AFaire => 'gray',
            self::EnCours => 'info',
            self::EnAttente => 'warning',
            self::Terminee => 'success',
            self::Annulee => 'gray',
            self::EnRetard => 'danger',
        };
    }
}
