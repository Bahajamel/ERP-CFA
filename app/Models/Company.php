<?php

namespace App\Models;

use App\Enums\CompanyStatut;
use App\Enums\NoteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    /** Candidats proposés à l'entreprise, à travers ses besoins (P0-03-4). */
    public function matchings(): HasManyThrough
    {
        return $this->hasManyThrough(Matching::class, Need::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /** Notes de type « incident » — pour l'indicateur de suivi (P0-03-5). */
    public function incidents(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->where('type', NoteType::Incident->value);
    }

    /** Notes de satisfaction, la plus récente en tête. */
    public function satisfactions(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')
            ->where('type', NoteType::Satisfaction->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** Dernier niveau de satisfaction mesuré (1 à 5), ou null si aucun. */
    public function derniereSatisfaction(): ?int
    {
        return $this->satisfactions()->value('satisfaction');
    }

    /** Interactions commerciales, la plus récente en tête (timeline). */
    public function interactions(): MorphMany
    {
        return $this->morphMany(Interaction::class, 'interactable')
            ->orderByDesc('date_interaction')
            ->orderByDesc('id');
    }

    /**
     * Prochaine relance planifiée : l'action datée définie par l'interaction la
     * plus récente qui en porte une. Null si aucune relance n'est planifiée.
     */
    public function prochaineRelance(): ?Interaction
    {
        return $this->interactions()
            ->whereNotNull('prochaine_action_le')
            ->first();
    }
}
