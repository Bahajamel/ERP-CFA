<?php

namespace App\Models;

use Database\Factories\OrganisationFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un CFA client de la plateforme (tenant Filament). Le CFA « maison » (V2S) est
 * une organisation comme les autres. Sert de racine à toutes les données métier :
 * chaque candidat, contrat, document… appartient à une organisation (Phase B).
 *
 * Porte aussi l'IDENTITÉ du CFA — raison sociale, SIRET, NDA, adresse,
 * représentant légal, référents, logo, signature, cachet — qui alimente les
 * CERFA, conventions, bulletins et livrables. Ces données vivaient dans un
 * singleton `cfa_profiles` sans rattachement : chaque CFA lisait la fiche du
 * premier. Un CFA = un enregistrement (migration 2026_07_25_000005).
 */
class Organisation extends Model implements HasMedia, HasName
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'verifier_rncp' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Logo : figure sur les documents générés → reste public.
        $this->addMediaCollection('logo')->singleFile();

        // Signature et cachet du CFA : détournables (risque de falsification) →
        // disque privé. Utilisés côté serveur (getPath) pour la génération PDF.
        $disquePrive = config('documents.disque_prive');
        $this->addMediaCollection('signature')->useDisk($disquePrive)->singleFile();
        $this->addMediaCollection('cachet')->useDisk($disquePrive)->singleFile();
    }

    /**
     * Le CFA dont on génère les documents : celui de la session, sinon le CFA
     * par défaut (jobs en file, commandes, formulaires publics — aucun tenant
     * courant n'y est défini).
     *
     * Remplace CfaProfile::current(), qui renvoyait la première ligne de la
     * table quel que soit le CFA connecté.
     */
    public static function courante(): self
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof self) {
            return $tenant;
        }

        return static::defaut() ?? static::query()->orderBy('id')->firstOrCreate(
            ['slug' => 'cfa'],
            ['nom' => config('cfa.nom', 'CFA'), 'actif' => true],
        );
    }

    /** Raison sociale si renseignée, sinon le nom — la désignation des documents. */
    public function designation(): string
    {
        return filled($this->raison_sociale) ? $this->raison_sociale : (string) $this->nom;
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
