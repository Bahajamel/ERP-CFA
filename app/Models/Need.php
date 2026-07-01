<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Matching\CompatibilityScorer;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

class Need extends Model
{
    use HasFactory;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_demarrage' => 'date',
            'date_cloture' => 'date',
            'nb_postes' => 'integer',
            'statut' => NeedStatut::class,
        ];
    }

    /** Statuts terminaux : le besoin est clos (plus de recrutement en cours). */
    public const STATUTS_CLOS = [
        NeedStatut::Pourvu,
        NeedStatut::Annule,
        NeedStatut::Archive,
    ];

    /** Le besoin est-il clôturé (statut terminal) ? */
    public function estCloture(): bool
    {
        return in_array($this->statut, self::STATUTS_CLOS, true);
    }

    /**
     * Nombre de postes encore à pourvoir (P0-04-4) = postes demandés moins les
     * candidats acceptés. Jamais négatif.
     */
    public function postesRestants(): int
    {
        $pourvus = $this->matchings()->where('statut', MatchingStatut::Accepte->value)->count();

        return max(0, (int) $this->nb_postes - $pourvus);
    }

    /** Besoins ouverts : hors statuts terminaux (P0-04-4). */
    public function scopeOuverts(Builder $query): Builder
    {
        return $query->whereNotIn(
            'statut',
            array_map(fn (NeedStatut $s): string => $s->value, self::STATUTS_CLOS),
        );
    }

    /**
     * Règle métier (P0-04-3) : un besoin ne peut être « Pourvu » que si un
     * candidat a été accepté (matching au statut « Accepté »).
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === NeedStatut::Pourvu && ! $this->matchings()->where('statut', MatchingStatut::Accepte->value)->exists()) {
            return 'Besoin non pourvu : aucun candidat accepté. Faites d\'abord accepter un candidat proposé.';
        }

        return null;
    }

    /**
     * Auto-clôture (P0-04-3). À l'entrée dans un statut terminal on date la
     * clôture ; en passant à « Pourvu » on clôt aussi les matchings encore
     * ouverts (refusés côté entreprise), en préservant le candidat accepté.
     */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if (in_array($to, self::STATUTS_CLOS, true) && $this->date_cloture === null) {
            $this->forceFill(['date_cloture' => now()])->save();
        }

        if ($to === NeedStatut::Pourvu) {
            $this->clotureMatchingsOuverts();
        }
    }

    /**
     * Clôt les propositions encore en cours (Proposé, CV envoyé, entretien,
     * attente retour) en « Abandonné » : le besoin étant pourvu, les autres
     * pistes sont abandonnées. Préserve les statuts terminaux et l'accepté.
     */
    protected function clotureMatchingsOuverts(): void
    {
        $ouverts = [
            MatchingStatut::Propose->value,
            MatchingStatut::CvEnvoye->value,
            MatchingStatut::EntretienPrevu->value,
            MatchingStatut::AttenteRetour->value,
        ];

        $this->matchings()
            ->whereIn('statut', $ouverts)
            ->get()
            ->each(function (Matching $matching): void {
                $matching->transitionTo(MatchingStatut::Abandonne, 'Clôture automatique : besoin pourvu.');
            });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'contact_id');
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'tuteur_id');
    }

    public function matchings(): HasMany
    {
        return $this->hasMany(Matching::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /**
     * Candidats compatibles avec ce besoin, classés par score décroissant.
     * Exclut les candidats déjà proposés et les ruptures. Chaque élément :
     * ['candidate' => Candidate, 'score' => int, 'explication' => string].
     */
    public function candidatsCompatibles(int $limit = 15): Collection
    {
        $dejaProposes = $this->matchings()->pluck('candidate_id')->all();
        $scorer = new CompatibilityScorer;

        return Candidate::query()
            ->whereNotIn('id', $dejaProposes)
            ->where('statut', '!=', CandidateStatut::Rupture->value)
            ->get()
            ->map(fn (Candidate $candidate): array => [
                'candidate' => $candidate,
                'score' => $scorer->score($candidate, $this),
                'explication' => $scorer->explication($candidate, $this),
            ])
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
