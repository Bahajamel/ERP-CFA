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
    case Heure = 'time';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Statut = 'statut';
    case Utilisateur = 'user';
    case Email = 'email';
    case Telephone = 'phone';
    case Url = 'url';
    case Montant = 'amount';
    case Pourcentage = 'percent';
    case Fichier = 'file';
    case Relation = 'relation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Texte court',
            self::Textarea => 'Texte long',
            self::Number => 'Nombre',
            self::Date => 'Date',
            self::Heure => 'Heure',
            self::Boolean => 'Oui / Non',
            self::Select => 'Liste déroulante',
            self::MultiSelect => 'Multi-sélection',
            self::Statut => 'Statut (coloré)',
            self::Utilisateur => 'Utilisateur assigné',
            self::Email => 'E-mail',
            self::Telephone => 'Téléphone',
            self::Url => 'Lien (URL)',
            self::Montant => 'Montant (€)',
            self::Pourcentage => 'Pourcentage',
            self::Fichier => 'Fichier (pièce jointe)',
            self::Relation => 'Relation (lien vers une fiche)',
        };
    }

    /** Ce type nécessite-t-il une liste d'options (config.options) ? */
    public function needsOptions(): bool
    {
        return in_array($this, [self::Select, self::Statut, self::MultiSelect], true);
    }

    /** Ce type cible-t-il une entité métier (config.related) ? */
    public function needsRelationTarget(): bool
    {
        return $this === self::Relation;
    }

    /** Le type stocke-t-il un tableau de valeurs (JSONB liste) ? */
    public function estMultiple(): bool
    {
        return $this === self::MultiSelect;
    }

    /** @return array<string, string> valeur => libellé, pour un select Filament. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t): array => [$t->value => $t->getLabel()])
            ->all();
    }
}
