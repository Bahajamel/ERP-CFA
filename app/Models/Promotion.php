<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

class Promotion extends Model
{
    use BelongsToOrganisation;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    /** Le libellé est toujours normalisé (pas d'espaces parasites → pas de cohortes fantômes). */
    protected function libelle(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null ? null : trim($value));
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /** Nom affichable : « Formation — 1ère année (2025-2026) ». */
    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn (): string => trim(
            implode(' — ', array_filter([$this->formation?->libelle, $this->libelle]))
            .($this->annee_scolaire ? " ({$this->annee_scolaire})" : '')
        ));
    }

    /** Les apprentis rattachés à cette cohorte (avec leur inscription : matières choisies, invitation). */
    public function apprentis(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class)
            ->using(CandidatePromotion::class)
            ->withPivot(['matieres', 'invitation_token', 'invited_at', 'responded_at'])
            ->withTimestamps();
    }

    /** Les séances (créneaux d'émargement) de la classe. */
    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    /** Les notes saisies dans cette classe. */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /** Documents rattachés à la classe (ex. examens/copies déposés en preuve). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Compose la classe : rattache les apprentis cochés et détache les autres.
     * Un apprenant peut suivre PLUSIEURS classes (matières), mais toutes au sein
     * de SA formation : un apprenant visant une autre formation est refusé, et
     * un apprenant sans formation renseignée adopte celle de la classe.
     */
    public function composerApprentis(array $candidateIds): void
    {
        if ($candidateIds !== [] && $this->formation_id !== null) {
            $incompatibles = Candidate::whereIn('id', $candidateIds)
                ->whereNotNull('formation_visee_id')
                ->where('formation_visee_id', '!=', $this->formation_id)
                ->pluck('nom')
                ->all();

            if ($incompatibles !== []) {
                throw ValidationException::withMessages([
                    'apprentis_ids' => 'Formation visée différente de celle de la classe : '.implode(', ', $incompatibles).'.',
                ]);
            }

            // Cohorte : un apprenant reste dans SON niveau (pas de 1ère année
            // dans une classe de 2ème année, et inversement).
            $horsCohorte = Candidate::whereIn('id', $candidateIds)
                ->whereHas('promotions', fn ($q) => $q
                    ->whereKeyNot($this->id)
                    ->where('libelle', '!=', $this->libelle))
                ->pluck('nom')
                ->all();

            if ($horsCohorte !== []) {
                throw ValidationException::withMessages([
                    'apprentis_ids' => 'Niveau (année) différent de celui de la classe : '.implode(', ', $horsCohorte).'.',
                ]);
            }

            // Un apprenant sans formation renseignée rejoint celle de la classe
            // (règle : toutes ses classes appartiennent à une seule formation).
            Candidate::whereIn('id', $candidateIds)
                ->whereNull('formation_visee_id')
                ->update(['formation_visee_id' => $this->formation_id]);
        }

        $this->apprentis()->sync($candidateIds);
    }
}
