<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Statut de présence d'un apprenti à une séance (émargement, EPIC-14). */
enum PresenceStatut: string implements HasColor, HasLabel
{
    case NonRenseigne = 'non_renseigne';
    case Present = 'present';
    case Retard = 'retard';
    case DepartAnticipe = 'depart_anticipe';
    case AbsentJustifie = 'absent_justifie';
    case AbsentInjustifie = 'absent_injustifie';

    public function getLabel(): string
    {
        return match ($this) {
            self::NonRenseigne => 'Non renseigné',
            self::Present => 'Présent',
            self::Retard => 'Retard',
            self::DepartAnticipe => 'Départ anticipé',
            self::AbsentJustifie => 'Absent justifié',
            self::AbsentInjustifie => 'Absent injustifié',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NonRenseigne => 'gray',
            self::Present => 'success',
            self::Retard, self::DepartAnticipe => 'warning',
            self::AbsentJustifie => 'info',
            self::AbsentInjustifie => 'danger',
        };
    }

    /** L'apprenti était-il absent (justifié ou non) ? */
    public function estAbsence(): bool
    {
        return in_array($this, [self::AbsentJustifie, self::AbsentInjustifie], true);
    }

    /** Statuts comptant comme « présent » pour le calcul d'assiduité. */
    public static function presents(): array
    {
        return [self::Present, self::Retard, self::DepartAnticipe];
    }
}
