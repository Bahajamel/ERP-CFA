<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'un {@see CustomTable} (tableau personnalisé). Les valeurs des colonnes
 * vivent dans `data` (JSONB { clé: valeur }). Cloisonné par CFA.
 */
class CustomRecord extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function customTable(): BelongsTo
    {
        return $this->belongsTo(CustomTable::class);
    }
}
