<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\NeedStatut;
use App\Matching\CompatibilityScorer;
use App\StateMachine\ManagesState;
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
            'nb_postes' => 'integer',
            'statut' => NeedStatut::class,
        ];
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
        $scorer = new CompatibilityScorer();

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

