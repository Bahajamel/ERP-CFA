<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use App\Models\Scopes\OrganisationScope;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattache un modèle métier à son CFA (organisation) — brique du multi-tenant
 * Filament natif. La colonne `organisation_id` porte l'appartenance ; Filament
 * s'appuie sur cette relation pour cloisonner les Resources, et le global scope
 * ci-dessous étend ce cloisonnement à toutes les requêtes Eloquent.
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        // Cloisonnement en lecture, y compris hors Filament (services, jobs,
        // widgets). Voir OrganisationScope pour le comportement hors contexte CFA.
        static::addGlobalScope(new OrganisationScope);

        /**
         * À la création, si aucune organisation n'est fixée et qu'un CFA courant est
         * défini, on rattache automatiquement l'enregistrement à ce CFA. Fiable au
         * niveau du modèle (Livewire, services, jobs), au-delà de l'observer Filament
         * qui, lui, dépend du contexte panel.
         */
        static::creating(function (Model $model): void {
            if ($model->getAttribute('organisation_id') === null
                && ($tenant = Filament::getTenant()) instanceof Organisation) {
                $model->setAttribute('organisation_id', $tenant->getKey());
            }
        });
    }

    /**
     * Lève le cloisonnement par CFA pour la requête en cours. À réserver aux cas
     * légitimes et explicites (panneau éditeur, statistiques inter-CFA, purge) :
     * dans le doute, ne pas l'utiliser.
     */
    public function scopeTousLesCfa(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OrganisationScope::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
