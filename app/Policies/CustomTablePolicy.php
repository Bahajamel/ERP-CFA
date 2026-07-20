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

    /** Voir : nécessite la capacité ET l'accès au tableau (privé + invitations). */
    public function view(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.view') && $table->accessiblePar($user);
    }

    public function create(User $user): bool
    {
        return $user->can('custom_tables.create');
    }

    /** Modifier : capacité ET niveau « modification » (ou gestionnaire du tableau). */
    public function update(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.update') && $table->modifiablePar($user);
    }

    /** Archivage (soft delete) : réservé au gestionnaire du tableau (créateur / supervision). */
    public function delete(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.delete') && $table->gerePar($user);
    }

    public function restore(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.update') && $table->gerePar($user);
    }

    /** Inviter / retirer des personnes : réservé au gestionnaire du tableau. */
    public function share(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.update') && $table->gerePar($user);
    }

    /** Suppression définitive : réservée à l'Administrateur. */
    public function forceDelete(User $user, CustomTable $table): bool
    {
        return $user->can('custom_tables.delete') && $user->hasRole('Administrateur');
    }
}
