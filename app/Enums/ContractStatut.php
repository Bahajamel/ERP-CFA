<?php

namespace App\Enums;

use App\StateMachine\DefinesTransitions;
use App\StateMachine\HasStateTransitions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatut: string implements HasLabel, HasColor, HasStateTransitions
{
    use DefinesTransitions;

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

    /**
     * Contrats « en cours » : apprentissage engagé (signé jusqu'à actif).
     * Population pertinente pour l'évaluation du risque de rupture.
     */
    public static function enCours(): array
    {
        return [self::Signe->value, self::TransmisOpco->value, self::Actif->value];
    }

    public function transitions(): array
    {
        return match ($this) {
            self::Brouillon => [self::InfosManquantes, self::PretAVerifier],
            self::InfosManquantes => [self::PretAVerifier, self::Brouillon],
            self::PretAVerifier => [self::EnvoyeSignature, self::InfosManquantes],
            self::EnvoyeSignature => [self::Signe, self::InfosManquantes],
            self::Signe => [self::TransmisOpco, self::Rompu],
            self::TransmisOpco => [self::Actif, self::Rompu],
            self::Actif => [self::Rompu, self::Archive],
            self::Rompu => [self::Archive],
            self::Archive => [],
        };
    }
}
