<?php

namespace App\Models;

use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Interaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'date_interaction' => 'date',
            'prochaine_action_le' => 'date',
        ];
    }

    public function interactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Interactions dont la relance est due (planifiée et à échéance atteinte). */
    public function scopeRelanceDue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('prochaine_action_le')
            ->whereDate('prochaine_action_le', '<=', now()->toDateString());
    }
}
