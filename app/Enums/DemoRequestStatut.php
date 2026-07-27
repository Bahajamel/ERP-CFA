<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Avancement d'une demande de démonstration, du premier contact jusqu'à
 * l'ouverture (ou non) d'un CFA client.
 */
enum DemoRequestStatut: string implements HasColor, HasLabel
{
    case Nouveau = 'nouveau';
    case Contacte = 'contacte';
    case DemoPlanifiee = 'demo_planifiee';
    case Converti = 'converti';
    case Refuse = 'refuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nouveau => 'Nouveau',
            self::Contacte => 'Contacté',
            self::DemoPlanifiee => 'Démonstration planifiée',
            self::Converti => 'Converti',
            self::Refuse => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Nouveau => 'info',
            self::Contacte => 'warning',
            self::DemoPlanifiee => 'primary',
            self::Converti => 'success',
            self::Refuse => 'danger',
        };
    }

    /** Demandes encore à traiter (utilisé pour le badge de navigation). */
    public static function aTraiter(): array
    {
        return [self::Nouveau, self::Contacte, self::DemoPlanifiee];
    }
}
