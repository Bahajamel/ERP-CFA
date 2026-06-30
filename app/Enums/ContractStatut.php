<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatut: string implements HasLabel, HasColor
{
    case Brouillon = 'brouillon';
    case InfosManquantes = 'infos_manquantes';
    case PretAVerifier = 'pret_a_verifier';
    case EnvoyeSignature = 'envoye_signature';
    case Signe = 'signe';
    case TransmisOpco = 'transmis_opco';
    case Actif = 'actif';
    case Rompu = 'rompu';
    case Archive = 'archive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::InfosManquantes => 'Informations manquantes',
            self::PretAVerifier => 'Prêt à vérifier',
            self::EnvoyeSignature => 'Envoyé pour signature',
            self::Signe => 'Signé',
            self::TransmisOpco => 'Transmis OPCO',
            self::Actif => 'Actif',
            self::Rompu => 'Rompu',
            self::Archive => 'Archivé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Brouillon => 'gray',
            self::InfosManquantes => 'danger',
            self::PretAVerifier => 'warning',
            self::EnvoyeSignature => 'info',
            self::Signe => 'success',
            self::TransmisOpco => 'info',
            self::Actif => 'success',
            self::Rompu => 'danger',
            self::Archive => 'gray',
        };
    }
}
