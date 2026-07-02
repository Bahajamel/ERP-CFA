<?php

namespace App\Models;

use App\Enums\PresenceStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Présence d'un apprenti à une séance (émargement, EPIC-14). Un justificatif
 * d'absence peut être rattaché (collection média « justificatif », P1-14-3).
 */
class Presence extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => PresenceStatut::class,
        ];
    }

    /**
     * Règle (P1-14-5) : une absence injustifiée crée/maj une tâche de suivi
     * (assignée au commercial de l'apprenti), retirée si le statut change.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $presence) => $presence->synchroniserAlerteAbsence());
        static::deleting(fn (self $presence) => Task::where('cle', $presence->cleAlerteAbsence())->delete());
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('justificatif')->singleFile();
    }

    public function cleAlerteAbsence(): string
    {
        return "absence:injustifiee:presence:{$this->id}";
    }

    public function synchroniserAlerteAbsence(): void
    {
        $cle = $this->cleAlerteAbsence();

        if ($this->statut !== PresenceStatut::AbsentInjustifie) {
            Task::where('cle', $cle)->delete();

            return;
        }

        $this->loadMissing('candidate', 'seance');

        Task::updateOrCreate(['cle' => $cle], [
            'titre' => 'Absence injustifiée — '.($this->candidate?->nom_complet ?? 'apprenti'),
            'description' => 'Séance du '.($this->seance?->date?->format('d/m/Y') ?? '—').' : absence injustifiée à traiter.',
            'taskable_type' => Candidate::class,
            'taskable_id' => $this->candidate_id,
            'assignee_id' => $this->candidate?->commercial_id,
            'due_date' => $this->seance?->date ?? now(),
            'priorite' => TaskPriorite::Haute->value,
            'statut' => TaskStatut::AFaire->value,
            'source' => 'auto',
        ]);
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** Un justificatif d'absence est-il joint ? */
    public function aJustificatif(): bool
    {
        return $this->getFirstMedia('justificatif') !== null;
    }
}
