<?php

namespace App\Models;

use App\Enums\CompanyStatut;
use App\Enums\NoteType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => CompanyStatut::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function opco(): BelongsTo
    {
        return $this->belongsTo(Opco::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function contactPrincipal(): HasMany
    {
        return $this->hasMany(CompanyContact::class)->where('is_principal', true);
    }

    /** Contacts marqués comme tuteurs (maîtres d'apprentissage) de l'entreprise. */
    public function tuteurs(): HasMany
    {
        return $this->hasMany(CompanyContact::class)->where('is_tuteur', true);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class);
    }

    /**
     * Formations pour lesquelles l'entreprise recherche activement des alternants,
     * déduites de ses besoins ouverts (libellés distincts). Une entreprise peut
     * recruter sur plusieurs formations à la fois (un besoin par formation).
     *
     * @return Collection<int, string>
     */
    public function formationsRecherchees(): Collection
    {
        return $this->needs()
            ->ouverts()
            ->with('formation')
            ->get()
            ->pluck('formation.libelle')
            ->filter()
            ->unique()
            ->values();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** Candidats proposés à l'entreprise, à travers ses besoins (P0-03-4). */
    public function matchings(): HasManyThrough
    {
        return $this->hasManyThrough(Matching::class, Need::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /** Notes de type « incident » — pour l'indicateur de suivi (P0-03-5). */
    public function incidents(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->where('type', NoteType::Incident->value);
    }

    /** Notes de satisfaction, la plus récente en tête. */
    public function satisfactions(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')
            ->where('type', NoteType::Satisfaction->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** Dernier niveau de satisfaction mesuré (1 à 5), ou null si aucun. */
    public function derniereSatisfaction(): ?int
    {
        return $this->satisfactions()->value('satisfaction');
    }

    /** Interactions commerciales, la plus récente en tête (timeline). */
    public function interactions(): MorphMany
    {
        return $this->morphMany(Interaction::class, 'interactable')
            ->orderByDesc('date_interaction')
            ->orderByDesc('id');
    }

    /**
     * Prochaine relance planifiée : l'action datée définie par l'interaction la
     * plus récente qui en porte une. Null si aucune relance n'est planifiée.
     */
    public function prochaineRelance(): ?Interaction
    {
        return $this->interactions()
            ->whereNotNull('prochaine_action_le')
            ->first();
    }

    /* ----------------------------------------------------------------
     |  Workspace « Focus entreprise »
     * ---------------------------------------------------------------- */

    /** Initiales (avatar sans logo) — ex. « Boulangerie Au Bon Pain » → « BAP ». */
    protected function initiales(): Attribute
    {
        return Attribute::get(function (): string {
            $mots = preg_split('/\s+/', trim((string) $this->raison_sociale)) ?: [];
            $lettres = collect($mots)
                ->filter()
                ->take(3)
                ->map(fn (string $m): string => mb_strtoupper(mb_substr($m, 0, 1)))
                ->implode('');

            return $lettres !== '' ? $lettres : '?';
        });
    }

    /** Nombre de besoins ouverts (postes à pourvoir). */
    public function besoinsOuvertsCount(): int
    {
        return $this->relationLoaded('needs')
            ? $this->needs->reject(fn (Need $n): bool => $n->estCloture())->count()
            : $this->needs()->ouverts()->count();
    }

    /**
     * Résumé de la dernière activité (dernière interaction) : libellé + date.
     *
     * @return array{label:?string,quand:?string}
     */
    public function derniereActivite(): array
    {
        $interaction = $this->relationLoaded('interactions')
            ? $this->interactions->first()
            : $this->interactions()->first();

        if ($interaction === null) {
            return ['label' => null, 'quand' => null];
        }

        return [
            'label' => $interaction->resume ?: $interaction->type->getLabel(),
            'quand' => $interaction->date_interaction?->diffForHumans(),
        ];
    }

    /**
     * Candidats les plus compatibles avec les besoins ouverts de l'entreprise,
     * agrégés et dédoublonnés (meilleur score conservé). Réutilise le moteur de
     * compatibilité des besoins ({@see Need::candidatsCompatibles()}).
     *
     * @return list<array{candidate:Candidate,score:int,besoin:string}>
     */
    public function matchingSuggere(int $limit = 3): array
    {
        return $this->needs()->ouverts()->get()
            ->flatMap(fn (Need $need) => $need->candidatsCompatibles(5)
                ->map(fn (array $row): array => [
                    'candidate' => $row['candidate'],
                    'score' => $row['score'],
                    'besoin' => $need->intitule_poste,
                ]))
            ->sortByDesc('score')
            ->unique(fn (array $row): int => $row['candidate']->id)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Conseil contextuel + prochaine action, selon l'état réel de l'entreprise
     * (statut, besoins ouverts, relance en attente). Évite les suggestions
     * hors-contexte.
     *
     * @return array{cle:string,libelle:string,tip:?string}
     */
    public function focusEntreprise(): array
    {
        $besoins = $this->besoinsOuvertsCount();
        $relanceDue = $this->interactions()->relanceDue()->exists()
            || ! $this->interactions()->where('date_interaction', '>=', now()->subDays(7))->exists();

        if ($this->statut === CompanyStatut::Prospect) {
            return ['cle' => 'prospect', 'libelle' => 'Convertir le prospect',
                'tip' => 'Planifiez un premier échange pour transformer ce prospect en partenaire.'];
        }

        if ($besoins > 0) {
            return ['cle' => 'besoins', 'libelle' => 'Pourvoir les besoins ouverts',
                'tip' => 'Des besoins sont ouverts : lancez le matching pour proposer les profils compatibles.'];
        }

        if ($relanceDue) {
            return ['cle' => 'relance', 'libelle' => 'Relancer l\'entreprise',
                'tip' => 'Sans activité récente : relancez pour identifier de nouveaux besoins de recrutement.'];
        }

        return ['cle' => 'suivi', 'libelle' => 'Partenariat actif',
            'tip' => 'Aucune action urgente. Gardez le contact pour anticiper les prochains besoins.'];
    }
}
