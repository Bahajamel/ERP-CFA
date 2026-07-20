<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ligne d'un {@see CustomTable} (tableau personnalisé). Les valeurs des colonnes
 * vivent dans `data` (JSONB { clé: valeur }). Cloisonnée par CFA, tracée (auteur
 * de création / dernière modification) et archivable (soft delete).
 *
 * @property array $data
 * @property ?int $created_by
 * @property ?int $updated_by
 */
class CustomRecord extends Model
{
    use BelongsToOrganisation;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['data'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ligne_personnalisee');
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomRecord $record): void {
            if (blank($record->created_by)) {
                $record->created_by = auth()->id();
            }
            $record->updated_by ??= auth()->id();
        });

        static::updating(function (CustomRecord $record): void {
            $record->updated_by = auth()->id();
        });
    }

    public function customTable(): BelongsTo
    {
        return $this->belongsTo(CustomTable::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
