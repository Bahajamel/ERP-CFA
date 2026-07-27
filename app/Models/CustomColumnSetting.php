<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

/**
 * Surcharge d'une colonne NATIVE d'une entité (couche « façon Monday ») : libellé
 * renommé et/ou largeur imposée, propres à un CFA. L'absence de ligne signifie que
 * la colonne garde son libellé et sa largeur d'origine. Cloisonné par CFA.
 *
 * @property string $entity
 * @property string $column_key
 * @property ?string $label
 * @property ?int $width
 * @property ?int $position
 */
class CustomColumnSetting extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'position' => 'integer',
        ];
    }
}
