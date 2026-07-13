<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattache un modèle métier à son CFA (organisation) — brique du multi-tenant
 * Filament natif. La colonne `organisation_id` porte l'appartenance ; Filament
 * s'appuie sur cette relation pour cloisonner automatiquement les données par CFA.
 */
trait BelongsToOrganisation
{
    /**
     * À la création, si aucune organisation n'est fixée et qu'un CFA courant est
     * défini, on rattache automatiquement l'enregistrement à ce CFA. Fiable au
     * niveau du modèle (Livewire, services, jobs), au-delà de l'observer Filament
     * qui, lui, dépend du contexte panel.
     */
    public static function bootBelongsToOrganisation(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('organisation_id') === null
                && ($tenant = Filament::getTenant()) instanceof Organisation) {
                $model->setAttribute('organisation_id', $tenant->getKey());
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
