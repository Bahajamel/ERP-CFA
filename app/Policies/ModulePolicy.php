<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Politique d'accès de base : mappe toutes les capacités (voir, créer, modifier,
 * supprimer, restaurer…) sur la permission du module (`access_<module>`).
 *
 * Elle formalise l'autorisation au niveau du modèle (Gate), et pas seulement dans
 * l'UI Filament : tout code (commande, futur endpoint API, `$this->authorize()`)
 * bénéficie du même verrou. Chaque modèle métier fournit sa permission via une
 * sous-classe. Le super-rôle « Administrateur » détient toutes les permissions,
 * donc passe partout.
 */
abstract class ModulePolicy
{
    /** Permission requise pour ce module (ex. « access_candidates »). */
    abstract protected function permission(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission());
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission());
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission());
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->permission());
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->can($this->permission());
    }

    public function reorder(User $user): bool
    {
        return $user->can($this->permission());
    }

    public function replicate(User $user, Model $model): bool
    {
        return $user->can($this->permission());
    }
}
