<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

/**
 * Définition d'une colonne personnalisée d'un CFA (couche « façon Monday »).
 * Isolée par organisation via {@see BelongsToOrganisation} : chaque CFA gère les
 * siennes sans jamais voir celles d'un autre. Les valeurs saisies vivent dans la
 * colonne JSONB `custom_fields` de l'entité concernée (ex. candidates).
 */
class CustomFieldDefinition extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'config' => 'array',
            'visible_table' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** Entités personnalisables (Phase 1 : Candidats). */
    public const ENTITES = [
        'candidate' => 'Candidats',
    ];
}
