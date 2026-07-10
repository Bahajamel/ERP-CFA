<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Statut « santé » d'une ligne financière — CALCULÉ (jamais stocké), toujours
 * à jour à partir des montants, factures et encaissements liés.
 * Ordre de priorité : Bloquée > En retard > Soldée > Encaissement en cours >
 * Partiellement facturée > À facturer.
 */
enum FinanceLineStatut: string implements HasColor, HasLabel
{
    case ABacturer = 'a_facturer';
    case PartiellementFacturee = 'partiellement_facturee';
    case EncaissementEnCours = 'encaissement_en_cours';
    case Soldee = 'soldee';
    case Bloquee = 'bloquee';
    case EnRetard = 'en_retard';

    public function getLabel(): string
    {
        return match ($this) {
            self::ABacturer => 'À facturer',
            self::PartiellementFacturee => 'Partiellement facturée',
            self::EncaissementEnCours => 'Encaissement en cours',
            self::Soldee => 'Soldée',
            self::Bloquee => 'Bloquée',
            self::EnRetard => 'En retard',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ABacturer => 'gray',
            self::PartiellementFacturee => 'info',
            self::EncaissementEnCours => 'warning',
            self::Soldee => 'success',
            self::Bloquee => 'danger',
            self::EnRetard => 'danger',
        };
    }
}
