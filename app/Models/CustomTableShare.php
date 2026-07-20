<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partage d'un {@see CustomTable} avec un utilisateur du CFA : niveau « lecture »
 * (voir seulement) ou « modification » (voir + éditer lignes/colonnes). Cloisonné
 * par CFA.
 *
 * @property int $custom_table_id
 * @property int $user_id
 * @property string $role
 */
class CustomTableShare extends Model
{
    use BelongsToOrganisation;

    public const ROLE_LECTURE = 'lecture';

    public const ROLE_MODIFICATION = 'modification';

    protected $guarded = [];

    /** Niveaux d'accès proposés (valeur => libellé). */
    public static function roles(): array
    {
        return [
            self::ROLE_LECTURE => 'Lecture',
            self::ROLE_MODIFICATION => 'Modification',
        ];
    }

    public function customTable(): BelongsTo
    {
        return $this->belongsTo(CustomTable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
