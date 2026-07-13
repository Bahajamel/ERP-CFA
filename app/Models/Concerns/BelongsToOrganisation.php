<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattache un modèle métier à son CFA (organisation) — brique du multi-tenant
 * Filament natif. La colonne `organisation_id` porte l'appartenance ; Filament
 * s'appuie sur cette relation pour cloisonner automatiquement les données par
 * CFA une fois la tenancy activée sur le panel (Phase C).
 */
trait BelongsToOrganisation
{
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
