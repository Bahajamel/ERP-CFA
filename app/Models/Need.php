<?php

namespace App\Models;

use App\Enums\NeedStatut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Need extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_demarrage' => 'date',
            'nb_postes' => 'integer',
            'statut' => NeedStatut::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'contact_id');
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'tuteur_id');
    }

    public function matchings(): HasMany
    {
        return $this->hasMany(Matching::class);
    }
}
