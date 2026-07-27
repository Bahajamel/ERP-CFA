<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    use BelongsToOrganisation;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duree_mois' => 'integer',
            'matieres' => 'array',
            'rncp_actif' => 'boolean',
            'rncp_verifie_at' => 'datetime',
        ];
    }

    /** Le programme de la formation : ses matières, nettoyées et sans doublon. */
    public function programme(): array
    {
        return collect($this->matieres ?? [])
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'formation_visee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** Les cohortes (« classes ») ouvertes sur cette formation. */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }
}
