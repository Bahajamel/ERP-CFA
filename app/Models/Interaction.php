<?php

namespace App\Models;

use App\Enums\InteractionType;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Interaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'date_interaction' => 'date',
            'prochaine_action_le' => 'date',
        ];
    }

    /**
     * Une prochaine action datée alimente automatiquement une tâche de relance
     * (P0-02-8 / P0-03-6) : créée/mise à jour à chaque enregistrement, supprimée
     * si l'action est retirée, et disparaît avec l'interaction.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $interaction) => $interaction->synchroniserTacheRelance());
        static::deleting(fn (self $interaction) => Task::where('cle', $interaction->cleTacheRelance())->delete());
    }

    /** Clé idempotente de la tâche de relance liée à cette interaction. */
    public function cleTacheRelance(): string
    {
        return "relance:interaction:{$this->id}";
    }

    /**
     * Crée/met à jour la tâche de relance (ou la supprime si plus d'action datée).
     * La tâche est reliée (polymorphe) à l'objet de l'interaction et assignée à
     * l'auteur ; échéance = date de relance.
     */
    public function synchroniserTacheRelance(): void
    {
        $cle = $this->cleTacheRelance();

        if ($this->prochaine_action_le === null) {
            Task::where('cle', $cle)->delete();

            return;
        }

        Task::updateOrCreate(['cle' => $cle], [
            'titre' => filled($this->prochaine_action) ? $this->prochaine_action : 'Relance à effectuer',
            'description' => 'Relance planifiée depuis une interaction commerciale.',
            'taskable_type' => $this->interactable_type,
            'taskable_id' => $this->interactable_id,
            'assignee_id' => $this->user_id,
            'due_date' => $this->prochaine_action_le,
            'priorite' => TaskPriorite::Normale->value,
            'statut' => TaskStatut::AFaire->value,
            'source' => 'relance',
        ]);
    }

    public function interactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Interactions dont la relance est due (planifiée et à échéance atteinte). */
    public function scopeRelanceDue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('prochaine_action_le')
            ->whereDate('prochaine_action_le', '<=', now()->toDateString());
    }
}
