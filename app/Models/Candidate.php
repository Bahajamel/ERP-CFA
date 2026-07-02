<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\ChecklistItemStatut;
use App\Enums\PresenceStatut;
use App\Matching\CompatibilityScorer;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Candidate extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'statut' => CandidateStatut::class,
        ];
    }

    /**
     * Règle métier (CDC §5 / P0-02-6) : un candidat doit avoir au moins un moyen
     * de contact — email OU téléphone. Invariant enforcé à chaque enregistrement.
     */
    protected static function booted(): void
    {
        static::saving(function (self $candidate): void {
            if (blank($candidate->email) && blank($candidate->telephone)) {
                throw ValidationException::withMessages([
                    'email' => 'Un candidat doit avoir au moins un email ou un téléphone.',
                ]);
            }
        });
    }

    /**
     * Règle métier (CDC §5 / P0-02-6) : blocage du passage à « Dossier complet »
     * tant qu'une pièce obligatoire de l'admission est manquante ou non conforme.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === CandidateStatut::Complet && $this->hasMissingRequiredPieces()) {
            return 'Dossier incomplet : des pièces obligatoires sont manquantes ou non conformes.';
        }

        return null;
    }

    /** Vrai s'il existe au moins une pièce obligatoire non « présente ». */
    public function hasMissingRequiredPieces(): bool
    {
        return (bool) $this->admission?->items()
            ->where('est_obligatoire', true)
            ->where('statut', '!=', ChecklistItemStatut::Presente->value)
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nom', 'prenom', 'email', 'telephone', 'statut', 'formation_visee_id', 'commercial_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('candidat');
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->prenom} {$this->nom}"));
    }

    public function formationVisee(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'formation_visee_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class);
    }

    public function matchings(): HasMany
    {
        return $this->hasMany(Matching::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /**
     * Assiduité de l'apprenti sur une période (EPIC-14, P1-14-4).
     * Retourne : séances renseignées, présents, absences injustifiées, taux (%).
     *
     * @return array{renseignees: int, presents: int, absences_injustifiees: int, taux: int|null}
     */
    public function assiduite(?string $du = null, ?string $au = null): array
    {
        $base = fn () => $this->presences()->whereHas('seance', function ($s) use ($du, $au): void {
            if ($du) {
                $s->whereDate('date', '>=', $du);
            }
            if ($au) {
                $s->whereDate('date', '<=', $au);
            }
        });

        $renseignees = $base()->where('statut', '!=', PresenceStatut::NonRenseigne->value)->count();
        $presents = $base()->whereIn('statut', array_map(
            fn (PresenceStatut $s) => $s->value,
            PresenceStatut::presents(),
        ))->count();
        $absInjustifiees = $base()->where('statut', PresenceStatut::AbsentInjustifie->value)->count();

        return [
            'renseignees' => $renseignees,
            'presents' => $presents,
            'absences_injustifiees' => $absInjustifiees,
            'taux' => $renseignees > 0 ? (int) round($presents / $renseignees * 100) : null,
        ];
    }

    /** Interactions commerciales, la plus récente en tête (timeline, P0-02-8). */
    public function interactions(): MorphMany
    {
        return $this->morphMany(Interaction::class, 'interactable')
            ->orderByDesc('date_interaction')
            ->orderByDesc('id');
    }

    /** Prochaine relance planifiée (action datée de l'interaction la plus récente). */
    public function prochaineRelance(): ?Interaction
    {
        return $this->interactions()->whereNotNull('prochaine_action_le')->first();
    }

    /**
     * Entreprises à cibler pour ce candidat (P1-03-7, Pilier E / F4). Deux signaux :
     *  1. un besoin ouvert compatible (score de compatibilité > 0) ;
     *  2. l'entreprise a déjà recruté dans la formation visée (partenaire chaud).
     * Classées : compatibilité décroissante d'abord, partenaires ensuite. Chaque
     * élément : ['company', 'score', 'besoin', 'raison'].
     */
    public function entreprisesACibler(int $limit = 15): Collection
    {
        $scorer = new CompatibilityScorer;

        // 1. Besoins ouverts compatibles → meilleure opportunité par entreprise.
        // Tableau natif indexé par company_id (modification imbriquée fiable).
        $cibles = Need::query()
            ->ouverts()
            ->with(['company', 'formation'])
            ->get()
            ->map(fn (Need $need): array => [
                'need' => $need,
                'score' => $scorer->score($this, $need),
            ])
            ->filter(fn (array $row): bool => $row['score'] > 0 && $row['need']->company !== null)
            ->groupBy(fn (array $row): int => $row['need']->company_id)
            ->map(function (Collection $rows): array {
                $meilleur = $rows->sortByDesc('score')->first();

                return [
                    'company' => $meilleur['need']->company,
                    'score' => $meilleur['score'],
                    'besoin' => $meilleur['need'],
                    'raison' => "Besoin ouvert : {$meilleur['need']->intitule_poste}",
                ];
            })
            ->all();

        // 2. Partenaires ayant déjà recruté dans la formation visée.
        if ($this->formation_visee_id !== null) {
            foreach (Company::query()->whereHas('contracts', fn ($q) => $q->where('formation_id', $this->formation_visee_id))->get() as $company) {
                if (isset($cibles[$company->id])) {
                    $cibles[$company->id]['raison'] .= ' · a déjà recruté dans cette formation';

                    continue;
                }

                $cibles[$company->id] = [
                    'company' => $company,
                    'score' => 0,
                    'besoin' => null,
                    'raison' => 'A déjà recruté dans cette formation',
                ];
            }
        }

        return collect($cibles)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
