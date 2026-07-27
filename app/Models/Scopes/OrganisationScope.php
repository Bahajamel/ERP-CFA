<?php

namespace App\Models\Scopes;

use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Cloisonne les données par CFA au niveau du modèle, et pas seulement dans les
 * Resources Filament : services, jobs, commandes, widgets et exports passent
 * tous par Eloquent et bénéficient donc de la même barrière.
 *
 * Hors contexte CFA (job planifié, commande artisan, seeding, panneau éditeur),
 * aucune restriction n'est appliquée : ces traitements balaient volontairement
 * l'ensemble des CFA. Depuis un contexte CFA, l'échappatoire explicite est
 * `Model::query()->tousLesCfa()`.
 */
class OrganisationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organisation) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('organisation_id'),
            $tenant->getKey(),
        );
    }
}
