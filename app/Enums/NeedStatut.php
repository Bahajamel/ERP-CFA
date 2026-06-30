<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NeedStatut: string implements HasLabel, HasColor
{
    case Cree = 'cree';
    case EnQualification = 'en_qualification';
    case ProfilsRecherches = 'profils_recherches';
    case ProfilsEnvoyes = 'profils_envoyes';
    case EntretienPrevu = 'entretien_prevu';
    case CandidatRetenu = 'candidat_retenu';
    case Pourvu = 'pourvu';
    case Annule = 'annule';
    case Archive = 'archive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cree => 'Besoin créé',
            self::EnQualification => 'En qualification',
            self::ProfilsRecherches => 'Profils recherchés',
            self::ProfilsEnvoyes => 'Profils envoyés',
            self::EntretienPrevu => 'Entretien prévu',
            self::CandidatRetenu => 'Candidat retenu',
            self::Pourvu => 'Besoin pourvu',
            self::Annule => 'Annulé',
            self::Archive => 'Archivé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cree => 'gray',
            self::EnQualification, self::ProfilsRecherches, self::ProfilsEnvoyes, self::EntretienPrevu => 'warning',
            self::CandidatRetenu => 'info',
            self::Pourvu => 'success',
            self::Annule => 'danger',
            self::Archive => 'gray',
        };
    }
}
