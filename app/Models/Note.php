<?php

namespace App\Models;

use App\Enums\NoteType;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends Model
{
    use BelongsToOrganisation;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'satisfaction' => 'integer',
        ];
    }

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
