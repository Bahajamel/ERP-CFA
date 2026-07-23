<?php

namespace App\Models;

use App\Enums\DemoRequestStatut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande de démonstration déposée depuis le site vitrine public.
 *
 * PAS de BelongsToOrganisation ici, et c'est volontaire : un prospect n'est
 * rattaché à aucun CFA — c'est justement ce qui sera décidé après la démo.
 * Ces demandes sont traitées dans le panneau /editeur.
 */
class DemoRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DemoRequestStatut::class,
            'consent_at' => 'datetime',
        ];
    }

    /** « Prénom NOM » du contact. */
    public function nomComplet(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Demandes encore ouvertes (ni converties, ni refusées). */
    public function scopeATraiter(Builder $query): Builder
    {
        return $query->whereIn(
            'status',
            array_map(fn (DemoRequestStatut $s): string => $s->value, DemoRequestStatut::aTraiter()),
        );
    }
}
