<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vue enregistrée d'un {@see CustomTable} : un agencement nommé (colonnes visibles,
 * ordre, tri, filtres) qu'un CFA peut rappeler d'un clic, avec une vue par défaut.
 * Cloisonnée par CFA.
 *
 * @property string $name
 * @property ?array $filters
 * @property ?array $sort
 * @property ?array $visible_columns
 * @property ?array $column_order
 * @property bool $is_default
 * @property ?int $created_by
 */
class CustomView extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sort' => 'array',
            'visible_columns' => 'array',
            'column_order' => 'array',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomView $view): void {
            $view->created_by ??= auth()->id();
        });

        // Une seule vue par défaut par tableau : poser is_default en retire l'ancienne.
        static::saved(function (CustomView $view): void {
            if ($view->is_default) {
                static::query()
                    ->where('custom_table_id', $view->custom_table_id)
                    ->whereKeyNot($view->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
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
}
