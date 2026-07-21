<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedOrigine;
use App\Enums\NeedStatut;
use App\Matching\CompatibilityScorer;
use App\Models\Concerns\BelongsToOrganisation;
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
    use BelongsToOrganisation;
    use HasFactory;
    use ManagesState;

    protected $guarded = [];

    /**
     * Le défaut de `origine` vit aussi côté modèle, pas seulement en base : sans
     * lui, une offre tout juste créée porte `origine = null` jusqu'au premier
     * refresh() et attendValidation() raisonnerait sur une valeur absente.
     */
    protected $attributes = [
        'origine' => 'interne',
    ];

    protected function casts(): array
    {
        return [
            'date_demarrage' => 'date',
            'date_cloture' => 'date',
            'nb_postes' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'rayon_km' => 'integer',
            'statut' => NeedStatut::class,
            'origine' => NeedOrigine::class,
            'validee_at' => 'datetime',
            'custom_fields' => 'array',
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

    /**
     * Besoins ouverts : hors statuts terminaux (P0-04-4) ET hors offres déposées
     * par une entreprise qu'aucun commercial n'a encore relues.
     *
     * Ce second filtre est le garde-fou de la fiche besoin publique : tant que
     * l'offre n'est pas validée elle ne doit générer aucune proposition de
     * candidat ni apparaître dans le pipeline. Les offres saisies par le CFA
     * (origine « interne », le défaut) ne sont jamais concernées.
     */
    public function scopeOuverts(Builder $query): Builder
    {
        return $query
            ->whereNotIn(
                'statut',
                array_map(fn (NeedStatut $s): string => $s->value, self::STATUTS_CLOS),
            )
            ->publiees();
    }

    /**
     * Offres déposées par une entreprise et pas encore relues par un commercial.
     *
     * Une offre rejetée passe en « Annulé » sans jamais être validée : le statut
     * terminal la sort de la file d'attente, sinon elle y resterait pour toujours.
     */
    public function scopeEnAttenteDeValidation(Builder $query): Builder
    {
        return $query
            ->where('origine', NeedOrigine::Entreprise->value)
            ->whereNull('validee_at')
            ->whereNotIn(
                'statut',
                array_map(fn (NeedStatut $s): string => $s->value, self::STATUTS_CLOS),
            );
    }

    /**
     * Offres entrées dans le circuit de recrutement : tout sauf les dépôts
     * d'entreprise en attente de relecture. Les offres saisies par le CFA le
     * sont d'emblée.
     */
    public function scopePubliees(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $q): Builder => $q->enAttenteDeValidation());
    }

    /** L'offre attend-elle une relecture commerciale ? */
    public function attendValidation(): bool
    {
        return $this->origine === NeedOrigine::Entreprise
            && $this->validee_at === null
            && ! $this->estCloture();
    }

    /**
     * Valide l'offre déposée par l'entreprise : elle rejoint les offres actives
     * (matching, pipeline). Idempotent — revalider ne redate pas la validation.
     */
    public function validerDepotEntreprise(): void
    {
        if (! $this->attendValidation()) {
            return;
        }

        $this->forceFill(['validee_at' => now()])->save();
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
     * Avancement du besoin, du moins avancé au plus avancé. Sert à ne progresser
     * QUE vers l'avant : un candidat refusé ne fait pas reculer le besoin (la
     * machine à états l'interdit d'ailleurs à partir de « Candidat retenu »).
     */
    private const PROGRESSION = [
        NeedStatut::Cree,
        NeedStatut::EnQualification,
        NeedStatut::ProfilsRecherches,
        NeedStatut::ProfilsEnvoyes,
        NeedStatut::EntretienPrevu,
        NeedStatut::CandidatRetenu,
        NeedStatut::Pourvu,
    ];

    /** Empêche la ré-entrance : clôturer un besoin modifie ses matchings. */
    private static bool $synchronisationEnCours = false;

    /**
     * Statut que l'activité réelle des candidats justifie, aujourd'hui.
     *
     * Le besoin est pourvu quand TOUS les postes le sont : une offre à 2 postes
     * dont un seul candidat est accepté continue de recruter.
     */
    public function statutJustifieParLesMatchings(): NeedStatut
    {
        // pluck() sur une relation applique les casts : on récupère des instances
        // de MatchingStatut, pas des chaînes. Comparer à `->value` ne matcherait
        // jamais (contrairement aux ->where(...) du reste du code, qui portent,
        // eux, sur la colonne brute).
        $statuts = $this->matchings()->pluck('statut');

        $acceptes = $statuts->filter(fn (MatchingStatut $s): bool => $s === MatchingStatut::Accepte)->count();

        return match (true) {
            $acceptes > 0 && $acceptes >= (int) $this->nb_postes => NeedStatut::Pourvu,
            $acceptes > 0 => NeedStatut::CandidatRetenu,
            $statuts->contains(MatchingStatut::EntretienEntreprise) => NeedStatut::EntretienPrevu,
            $statuts->contains(MatchingStatut::PropositionEnvoyee) => NeedStatut::ProfilsEnvoyes,
            $statuts->contains(MatchingStatut::EnRecherche) => NeedStatut::ProfilsRecherches,
            default => NeedStatut::Cree,
        };
    }

    /**
     * Aligne le statut du besoin sur l'activité réelle des candidats — appelé à
     * chaque évolution d'un matching (cf. Matching::booted).
     *
     * Personne ne tenait ces statuts à jour à la main : ils restaient figés sur
     * « Créé » et la colonne ne voulait plus rien dire. Le besoin traverse ici
     * les étapes une à une, en passant par transitionTo() : les garde-fous, la
     * clôture automatique et l'historique d'activité s'appliquent normalement.
     *
     * Un besoin clos (pourvu, annulé, archivé) n'est jamais rouvert, et on ne
     * revient jamais en arrière.
     */
    public function synchroniserDepuisMatchings(): void
    {
        if (self::$synchronisationEnCours || $this->estCloture()) {
            return;
        }

        $cible = $this->statutJustifieParLesMatchings();

        $rang = fn (NeedStatut $s): int|false => array_search($s, self::PROGRESSION, true);
        $depuis = $rang($this->statut);

        // Statut hors trajectoire nominale (annulé…) ou déjà au-delà : on ne touche à rien.
        if ($depuis === false || $rang($cible) === false || $rang($cible) <= $depuis) {
            return;
        }

        self::$synchronisationEnCours = true;

        try {
            // Les transitions sont enchaînées pas à pas : la machine à états
            // n'autorise pas les raccourcis (« Créé » ne mène pas directement à
            // « Profils envoyés »), et chaque étape reste tracée.
            foreach (array_slice(self::PROGRESSION, $depuis + 1, $rang($cible) - $depuis) as $etape) {
                $this->transitionTo($etape, 'Mise à jour automatique depuis les candidats proposés.');
            }
        } finally {
            self::$synchronisationEnCours = false;
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
            MatchingStatut::EnRecherche->value,
            MatchingStatut::PropositionEnvoyee->value,
            MatchingStatut::EntretienEntreprise->value,
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

    /** Pièces rattachées à l'offre en GED (dont la fiche besoin générée). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
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
            // Cycle apprenant : seuls les candidats acceptés par le CFA
            // entrent en recherche d'entreprise.
            ->where('statut', CandidateStatut::Accepte->value)
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
