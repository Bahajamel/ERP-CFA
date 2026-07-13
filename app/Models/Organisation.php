<?php

namespace App\Models;

use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un CFA client de la plateforme (tenant Filament). Le CFA « maison » (V2S) est
 * une organisation comme les autres. Sert de racine à toutes les données métier :
 * chaque candidat, contrat, document… appartiendra à une organisation (Phase B).
 */
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    protected $fillable = ['nom', 'slug', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** Le slug sert d'identifiant d'URL du tenant. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Utilisateurs (personnel) rattachés à ce CFA. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
