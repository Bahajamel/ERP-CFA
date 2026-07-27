<?php

namespace App\Models;

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use BelongsToOrganisation;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'priorite' => TaskPriorite::class,
            'statut' => TaskStatut::class,
        ];
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
