<?php

namespace App\Models;

use App\Enums\CompanyStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => CompanyStatut::class,
        ];
    }

    public function opco(): BelongsTo
    {
        return $this->belongsTo(Opco::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function contactPrincipal(): HasMany
    {
        return $this->hasMany(CompanyContact::class)->where('is_principal', true);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
