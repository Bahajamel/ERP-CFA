<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Niveau de risque de rupture d'un contrat d'apprentissage, dérivé d'un score
 * pondéré (0–100). Les seuils reflètent l'urgence d'intervention de l'équipe.
 */
enum RiskLevel: string implements HasColor, HasLabel
{
    case Faible = 'faible';
    case Modere = 'modere';
    case Eleve = 'eleve';
    case Critique = 'critique';

    /** Traduit un score 0–100 en niveau de risque. */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 60 => self::Critique,
            $score >= 35 => self::Eleve,
            $score >= 15 => self::Modere,
            default => self::Faible,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Faible => 'Faible',
            self::Modere => 'Modéré',
            self::Eleve => 'Élevé',
            self::Critique => 'Critique',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Faible => 'success',
            self::Modere => 'warning',
            self::Eleve => 'danger',
            self::Critique => 'danger',
        };
    }

    /** Niveaux justifiant une alerte / une intervention proactive. */
    public static function aRisque(): array
    {
        return [self::Eleve->value, self::Critique->value];
    }
}
