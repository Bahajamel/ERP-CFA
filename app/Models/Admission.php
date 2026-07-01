<?php

namespace App\Models;

use App\Enums\AdmissionStatut;
use App\StateMachine\ManagesState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    use HasFactory;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => AdmissionStatut::class,
            'validated_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdmissionChecklistItem::class);
    }
}
