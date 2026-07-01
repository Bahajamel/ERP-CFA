<?php

namespace App\StateMachine;

use BackedEnum;

/**
 * À utiliser sur les modèles Eloquent portant un statut à machine à états.
 *
 * Pré-requis : la colonne d'état est castée vers un enum implémentant
 * {@see HasStateTransitions}. Par défaut la colonne est « statut » ; un modèle
 * peut la surcharger via stateColumn() (ex. Contract → « statut_contrat »).
 *
 * @property-read \Illuminate\Database\Eloquent\Model $this
 */
trait ManagesState
{
    /** Nom de la colonne portant l'état. */
    public function stateColumn(): string
    {
        return 'statut';
    }

    /** État courant (instance d'enum). */
    public function currentState(): HasStateTransitions
    {
        return $this->{$this->stateColumn()};
    }

    /**
     * États réellement atteignables (structure + gardes métier).
     * Sert à l'UI (boutons de transition, colonnes Kanban cibles).
     *
     * @return array<int, HasStateTransitions>
     */
    public function allowedTransitions(): array
    {
        return array_values(array_filter(
            $this->currentState()->transitions(),
            fn (HasStateTransitions $to): bool => $this->transitionBlockReason($to) === null,
        ));
    }

    /**
     * Motif de blocage d'une transition, ou null si elle est autorisée.
     * Combine la structure (enum) et la règle métier ({@see guardTransition()}).
     */
    public function transitionBlockReason(HasStateTransitions $to): ?string
    {
        $from = $this->currentState();

        if (! $from->canTransitionTo($to)) {
            return "Transition non autorisée : « {$from->getLabel()} » → « {$to->getLabel()} ».";
        }

        return $this->guardTransition($from, $to);
    }

    public function canTransitionTo(HasStateTransitions $to): bool
    {
        return $this->transitionBlockReason($to) === null;
    }

    /**
     * Règle métier bloquante propre au modèle. Retourne un motif (string) si la
     * transition doit être refusée, null sinon. À surcharger par modèle.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        return null;
    }

    /**
     * Applique la transition (après validation), persiste et journalise.
     *
     * @throws InvalidTransitionException si la transition est refusée
     */
    public function transitionTo(HasStateTransitions $to, ?string $comment = null): void
    {
        if ($reason = $this->transitionBlockReason($to)) {
            throw new InvalidTransitionException($reason);
        }

        $from = $this->currentState();

        $this->{$this->stateColumn()} = $to;
        $this->save();

        $this->afterTransition($from, $to, $comment);
    }

    /**
     * Point d'accroche pour les effets de bord (tâches auto, actions
     * correctives…). À terme relié au moteur de règles (business_rules).
     */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
    }
}
