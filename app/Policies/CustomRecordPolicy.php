<?php

namespace App\Policies;

use App\Models\CustomRecord;
use App\Models\User;

/**
 * Accès aux LIGNES des tables personnalisées. Capacités granulaires distinctes de
 * la gestion des tables : un CFA peut autoriser un rôle à saisir/éditer des lignes
 * sans pour autant lui laisser créer ou supprimer des tables. Suppression
 * définitive réservée à l'Administrateur ; sinon archivage (soft delete).
 */
class CustomRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('custom_records.view');
    }

    public function view(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.view');
    }

    public function create(User $user): bool
    {
        return $user->can('custom_records.create');
    }

    public function update(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.update');
    }

    /** Archivage (soft delete) d'une ligne. */
    public function delete(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.delete');
    }

    public function restore(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.update');
    }

    /** Suppression définitive : réservée à l'Administrateur. */
    public function forceDelete(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.delete') && $user->hasRole('Administrateur');
    }
}
