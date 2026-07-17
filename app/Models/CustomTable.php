<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tableau personnalisé d'un CFA (couche « façon Monday » — Phase 3). Porte un
 * nom et des colonnes (custom_field_definitions rattachées via custom_table_id) ;
 * ses lignes sont des {@see CustomRecord}. Cloisonné par CFA.
 */
class CustomTable extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    /** Colonnes du tableau (définitions rattachées), ordonnées. */
    public function colonnes(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class)->orderBy('sort')->orderBy('id');
    }

    /** Lignes du tableau. */
    public function records(): HasMany
    {
        return $this->hasMany(CustomRecord::class);
    }
}
