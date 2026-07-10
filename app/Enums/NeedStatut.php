<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NeedStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

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

    public function transitions(): array
    {
        return match ($this) {
            self::Cree => [self::EnQualification, self::Annule],
            self::EnQualification => [self::ProfilsRecherches, self::Annule],
            self::ProfilsRecherches => [self::ProfilsEnvoyes, self::Annule],
            self::ProfilsEnvoyes => [self::EntretienPrevu, self::CandidatRetenu, self::Annule],
            self::EntretienPrevu => [self::CandidatRetenu, self::ProfilsEnvoyes, self::Annule],
            self::CandidatRetenu => [self::Pourvu, self::Annule],
            self::Pourvu => [self::Archive],
            self::Annule => [self::Archive],
            self::Archive => [],
        };
    }

    /** Colonnes de l'entonnoir de recrutement (Kanban). Exclut les états terminaux. */
    public static function board(): array
    {
        return [
            self::Cree,
            self::EnQualification,
            self::ProfilsRecherches,
            self::ProfilsEnvoyes,
            self::EntretienPrevu,
            self::CandidatRetenu,
            self::Pourvu,
        ];
    }
}
