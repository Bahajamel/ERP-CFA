<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyContact extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_principal' => 'boolean',
            'is_tuteur' => 'boolean',
            'is_representant_legal' => 'boolean',
            'is_responsable_financier' => 'boolean',
            'is_contact_facturation' => 'boolean',
            'date_naissance' => 'date',
        ];
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->prenom} {$this->nom}"));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
