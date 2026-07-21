<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * D'où vient l'offre : saisie par le CFA, ou déposée par l'entreprise elle-même
 * via la fiche besoin publique (2ᵉ étape du formulaire /entreprise).
 *
 * Seules les offres « entreprise » passent par une validation commerciale avant
 * d'entrer dans le circuit de recrutement (cf. Need::scopeEnAttenteDeValidation).
 */
enum NeedOrigine: string implements HasColor, HasLabel
{
    case Interne = 'interne';
    case Entreprise = 'entreprise';

    public function getLabel(): string
    {
        return match ($this) {
            self::Interne => 'Saisie par le CFA',
            self::Entreprise => 'Déposée par l\'entreprise',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Interne => 'gray',
            self::Entreprise => 'info',
        };
    }
}
