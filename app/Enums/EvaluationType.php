<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Nature d'une évaluation (note) d'un apprenant. */
enum EvaluationType: string implements HasColor, HasLabel
{
    case Devoir = 'devoir';
    case Controle = 'controle';
    case Examen = 'examen';
    case Projet = 'projet';
    case Oral = 'oral';
    case TP = 'tp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Devoir => 'Devoir',
            self::Controle => 'Contrôle',
            self::Examen => 'Examen',
            self::Projet => 'Projet',
            self::Oral => 'Oral',
            self::TP => 'Travaux pratiques',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Examen => 'danger',
            self::Controle => 'warning',
            self::Projet => 'info',
            self::Oral => 'success',
            default => 'gray',
        };
    }
}
