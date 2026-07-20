<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Définition d'une colonne personnalisée d'un CFA (couche « façon Monday »).
 * Isolée par organisation via {@see BelongsToOrganisation} : chaque CFA gère les
 * siennes sans jamais voir celles d'un autre. Les valeurs saisies vivent dans la
 * colonne JSONB `custom_fields` de l'entité concernée (ex. candidates).
 */
class CustomFieldDefinition extends Model
{
    use BelongsToOrganisation;
    use LogsActivity;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'type', 'is_required', 'visible_table'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('colonne_personnalisee');
    }

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'config' => 'array',
            'visible_table' => 'boolean',
            'sort' => 'integer',
            'is_required' => 'boolean',
            'default_value' => 'array',
            'validation_rules' => 'array',
        ];
    }

    /** Entités personnalisables (Phase 1 : Candidats). */
    public const ENTITES = [
        'candidate' => 'Candidats',
    ];
}
