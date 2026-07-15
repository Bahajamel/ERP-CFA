<?php

namespace App\Models;

use Database\Factories\OrganisationFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un CFA client de la plateforme (tenant Filament). Le CFA « maison » (V2S) est
 * une organisation comme les autres. Sert de racine à toutes les données métier :
 * chaque candidat, contrat, document… appartient à une organisation (Phase B).
 */
class Organisation extends Model implements HasName
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

    /** Nom du CFA affiché par Filament (sélecteur de tenant, menu). */
    public function getFilamentName(): string
    {
        return $this->nom;
    }

    /** Utilisateurs (personnel) rattachés à ce CFA. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Candidats du CFA. Sert au panneau éditeur (volumétrie par client) ; les
     * autres entités métier restent accessibles via leur `organisation_id`.
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * CFA par défaut, utilisé par les points d'entrée hors panel (formulaires
     * publics de candidature / entreprise) où aucun tenant courant n'est défini.
     * En mono-CFA, c'est le CFA « maison ». (Multi-CFA : des liens publics dédiés
     * par CFA porteront l'organisation cible.)
     */
    public static function defaut(): ?self
    {
        return static::query()->where('actif', true)->orderBy('id')->first();
    }
}
