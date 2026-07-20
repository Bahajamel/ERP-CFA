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

    /** Voir une ligne : capacité ET accès au tableau parent (privé + invitations). */
    public function view(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.view') && ($record->customTable?->accessiblePar($user) ?? false);
    }

    public function create(User $user): bool
    {
        return $user->can('custom_records.create');
    }

    /** Modifier une ligne : capacité ET niveau « modification » sur le tableau parent. */
    public function update(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.update') && ($record->customTable?->modifiablePar($user) ?? false);
    }

    /** Archivage (soft delete) d'une ligne : idem modification. */
    public function delete(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.delete') && ($record->customTable?->modifiablePar($user) ?? false);
    }

    public function restore(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.update') && ($record->customTable?->modifiablePar($user) ?? false);
    }

    /** Suppression définitive : réservée à l'Administrateur. */
    public function forceDelete(User $user, CustomRecord $record): bool
    {
        return $user->can('custom_records.delete') && $user->hasRole('Administrateur');
    }
}
