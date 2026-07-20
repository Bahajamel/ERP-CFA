<?php

namespace App\Enums;

use App\Support\CustomFields;

/**
 * Types de colonnes personnalisées qu'un CFA peut créer (couche « façon Monday »).
 * Le rendu (champ de formulaire / colonne de tableau) est construit dans
 * {@see CustomFields}.
 */
enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case Statut = 'statut';
    case Utilisateur = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Texte court',
            self::Textarea => 'Texte long',
            self::Number => 'Nombre',
            self::Date => 'Date',
            self::Boolean => 'Oui / Non',
            self::Select => 'Liste déroulante',
            self::Statut => 'Statut (coloré)',
            self::Utilisateur => 'Utilisateur assigné',
        };
    }

    /** Ce type nécessite-t-il une liste d'options (config.options) ? */
    public function needsOptions(): bool
    {
        return $this === self::Select || $this === self::Statut;
    }

    /** @return array<string, string> valeur => libellé, pour un select Filament. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t): array => [$t->value => $t->getLabel()])
            ->all();
    }
}
