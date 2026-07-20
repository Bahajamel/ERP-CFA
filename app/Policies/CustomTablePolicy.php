<?php

namespace App\Policies;

use App\Models\CustomTable;
use App\Models\User;

/**
 * Accès aux TABLES personnalisées (couche « façon Monday »). Contrairement aux
 * modules métier (une seule permission par module), la personnalisation est
 * gouvernée par des capacités granulaires : voir, créer, modifier, supprimer.
 * La suppression DÉFINITIVE reste réservée à l'Administrateur ; le reste passe
 * par l'archivage (soft delete) via delete().
 */
class CustomTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('custom_tables.view');
    }

    public function view(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.view');
    }

    public function create(User $user): bool
    {
        return $user->can('custom_tables.create');
    }

    public function update(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.update');
    }

    /** Archivage (soft delete) d'une table. */
    public function delete(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.delete');
    }

    public function restore(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.update');
    }

    /** Suppression définitive : réservée à l'Administrateur. */
    public function forceDelete(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.delete') && $user->hasRole('Administrateur');
    }
}
