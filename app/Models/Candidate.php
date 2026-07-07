<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\DocumentType;
use App\Enums\PresenceStatut;
use App\Matching\CompatibilityScorer;
use App\Observers\CandidateObserver;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(CandidateObserver::class)]
class Candidate extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use ManagesState;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_disponibilite' => 'date',
            'statut' => CandidateStatut::class,
        ];
    }

    /** Types MIME acceptés pour les pièces justificatives (PDF ou image). */
    private const MIMES_JUSTIFICATIFS = ['application/pdf', 'image/jpeg', 'image/png'];

    /**
     * Pièces du candidat, chacune dans sa collection média à fichier unique :
     * - `cv` : seul document requis pour valider la pré-admission ;
     * - `piece_identite` : carte d'identité, titre de séjour, passeport… ;
     * - `carte_vitale` : Carte Vitale ou attestation de sécurité sociale ;
     * - `attestation_projet` : attestation de création de projet, exigée
     *   uniquement pour les candidats de plus de 30 ans (dérogation d'âge
     *   du contrat d'apprentissage).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cv')
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);

        $this->addMediaCollection('piece_identite')->singleFile()->acceptsMimeTypes(self::MIMES_JUSTIFICATIFS);
        $this->addMediaCollection('carte_vitale')->singleFile()->acceptsMimeTypes(self::MIMES_JUSTIFICATIFS);
        $this->addMediaCollection('attestation_projet')->singleFile()->acceptsMimeTypes(self::MIMES_JUSTIFICATIFS);
    }

    /**
     * Le candidat a-t-il 30 ans ou plus ? Au-delà de 29 ans révolus, l'entrée
     * en apprentissage relève d'une dérogation : l'attestation de création de
     * projet est alors exigée au dossier.
     */
    public function plusDe30Ans(): bool
    {
        return self::dateNaissancePlusDe30Ans($this->date_naissance);
    }

    /** Même règle (≥ 30 ans), applicable à une valeur brute de formulaire. */
    public static function dateNaissancePlusDe30Ans(mixed $dateNaissance): bool
    {
        if (blank($dateNaissance)) {
            return false;
        }

        try {
            return Carbon::parse($dateNaissance)->age >= 30;
        } catch (\Throwable) {
            return false;
        }
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
     * Règle métier (pré-candidature) : blocage du passage à « Dossier complet »
     * tant que le CV — seul document requis à cette étape — n'est pas fourni.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === CandidateStatut::Complet && ! $this->hasCv()) {
            return 'Dossier incomplet : le CV du candidat est manquant.';
        }

        return null;
    }

    /**
     * Le candidat a-t-il un CV ? Vrai si un fichier existe dans la collection
     * média « cv » OU s'il possède un document GED de type CV avec un fichier
     * (les deux sources sont acceptées — détection centralisée et fiable).
     */
    public function hasCv(): bool
    {
        if ($this->getFirstMedia('cv') !== null) {
            return true;
        }

        return $this->documents()
            ->where('type', DocumentType::CvCandidat->value)
            ->whereHas('media')
            ->exists();
    }

    /** URL de téléchargement du CV (média « cv » en priorité, sinon document GED). */
    public function cvUrl(): ?string
    {
        return $this->cvInfo()['url'] ?? null;
    }

    /**
     * Métadonnées du CV pour l'affichage (nom du fichier, type, date d'ajout,
     * URL), quelle que soit la source (média « cv » ou document GED de type CV).
     * Le fichier n'est jamais dupliqué : la pré-admission réutilise la même pièce.
     *
     * @return array{name:string, mime:?string, extension:?string, added_at:?Carbon, url:string}|null
     */
    public function cvInfo(): ?array
    {
        if (($media = $this->getFirstMedia('cv')) !== null) {
            return [
                'name' => $media->file_name,
                'mime' => $media->mime_type,
                'extension' => $media->extension,
                'added_at' => $media->created_at,
                'url' => $media->getUrl(),
            ];
        }

        $doc = $this->documents()
            ->where('type', DocumentType::CvCandidat->value)
            ->whereHas('media')
            ->latest()
            ->first();

        $media = $doc?->getFirstMedia('fichier');

        if ($media === null) {
            return null;
        }

        return [
            'name' => $media->file_name,
            'mime' => $media->mime_type,
            'extension' => $media->extension,
            'added_at' => $media->created_at,
            'url' => $media->getUrl(),
        ];
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

    /** Les classes (matières) suivies — toutes au sein de SA formation. */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class)->withTimestamps();
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

    /** Périodes de disponibilité / indisponibilité du candidat (structure évolutive). */
    public function availabilities(): HasMany
    {
        return $this->hasMany(CandidateAvailability::class);
    }

    /** Ids des missions CFA (L6231-2) couvertes par au moins un document de l'apprenti. */
    public function missionsCouvertesIds(): Collection
    {
        return $this->documents()
            ->join('cfa_mission_document', 'documents.id', '=', 'cfa_mission_document.document_id')
            ->distinct()
            ->pluck('cfa_mission_document.cfa_mission_id');
    }

    /**
     * Couverture des 14 missions pour cet apprenti : chaque mission avec un
     * drapeau « couverte » (au moins un livrable rattaché). Sert de vue d'audit.
     *
     * @return Collection<int, object{mission: CfaMission, couverte: bool}>
     */
    public function couvertureMissions(): Collection
    {
        $couvertes = $this->missionsCouvertesIds();

        return CfaMission::query()->orderBy('numero')->get()->map(fn (CfaMission $mission) => (object) [
            'mission' => $mission,
            'couverte' => $couvertes->contains($mission->id),
        ]);
    }

    /** Taux de couverture des 14 missions pour cet apprenti (0-100). */
    public function tauxCouvertureMissions(): int
    {
        $total = CfaMission::query()->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->missionsCouvertesIds()->count() * 100 / $total);
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
