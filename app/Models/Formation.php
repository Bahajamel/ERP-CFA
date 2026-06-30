<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duree_mois' => 'integer',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'formation_visee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
