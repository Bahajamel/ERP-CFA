<?php

namespace App\Models;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\DocumentType;
use App\Enums\EntretienStatut;
use App\Enums\PresenceStatut;
use App\Http\Controllers\CandidatureController;
use App\Matching\CompatibilityScorer;
use App\Models\Concerns\BelongsToOrganisation;
use App\Parcours\CycleApprenant;
use App\StateMachine\HasStateTransitions;
use App\StateMachine\ManagesState;
use App\Support\SecureMedia;
use Illuminate\Database\Eloquent\Builder;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Candidate extends Model implements HasMedia
{
    use BelongsToOrganisation;
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use ManagesState {
        transitionBlockReason as private baseTransitionBlockReason;
    }
    use SoftDeletes;

    protected $guarded = [];

    /** Délai de rétention en corbeille avant purge définitive automatique. */
    public const DELAI_PURGE_JOURS = 30;

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_disponibilite' => 'date',
            'cv_consentement' => 'boolean',
            'cv_consentement_at' => 'datetime',
            'statut' => CandidateStatut::class,
            // Valeurs des colonnes personnalisées du CFA (couche « façon Monday »).
            'custom_fields' => 'array',
        ];
    }

    /**
     * Archive le candidat en corbeille : trace le motif (obligatoire) et
     * l'auteur, puis soft-delete. Il disparaît alors de toutes les listes
     * (ses dossiers sont masqués via `whereHas('candidate')`) et reste
     * restaurable 30 jours avant purge automatique.
     */
    public function archiver(string $motif, ?int $parUtilisateur = null): void
    {
        $this->forceFill([
            'motif_suppression' => $motif,
            'deleted_by' => $parUtilisateur ?? auth()->id(),
        ])->saveQuietly();

        $this->delete();
    }

    /** Restaure un candidat depuis la corbeille (efface le motif et l'auteur). */
    public function restaurer(): void
    {
        $this->restore();
        $this->forceFill(['motif_suppression' => null, 'deleted_by' => null])->saveQuietly();
    }

    /** Jours restants avant la purge définitive (0 si l'échéance est atteinte). */
    public function joursAvantPurge(): ?int
    {
        if ($this->deleted_at === null) {
            return null;
        }

        $purgeLe = $this->deleted_at->copy()->addDays(self::DELAI_PURGE_JOURS);

        return max(0, (int) ceil(now()->diffInDays($purgeLe, false)));
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
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
        // Pièces sensibles (données personnelles / NIR) : disque PRIVÉ, jamais
        // d'URL publique — accès uniquement via la route sécurisée signée.
        $disquePrive = config('documents.disque_prive');

        $this->addMediaCollection('cv')
            ->useDisk($disquePrive)
            ->singleFile()
            ->acceptsMimeTypes([
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);

        $this->addMediaCollection('piece_identite')->useDisk($disquePrive)->singleFile()->acceptsMimeTypes(self::MIMES_JUSTIFICATIFS);
        $this->addMediaCollection('carte_vitale')->useDisk($disquePrive)->singleFile()->acceptsMimeTypes(self::MIMES_JUSTIFICATIFS);
        $this->addMediaCollection('attestation_projet')->useDisk($disquePrive)->singleFile()->acceptsMimeTypes(self::MIMES_JUSTIFICATIFS);

        // Photo de profil de l'apprenant (fiche apprenant, trombinoscope) :
        // faible sensibilité, affichée en <img> inline → reste sur le disque public.
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
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

            // Horodate le consentement CV dès qu'il est donné, l'efface s'il est retiré.
            if ($candidate->isDirty('cv_consentement')) {
                $candidate->cv_consentement_at = $candidate->cv_consentement
                    ? ($candidate->cv_consentement_at ?? now())
                    : null;
            }
        });

        // Invariant backend (cycle apprenant) : une décision finale (Accepté /
        // Refusé) est irréversible, quel que soit le chemin d'écriture — pas
        // seulement via la machine à états.
        static::updating(function (self $candidate): void {
            if (! $candidate->isDirty('statut')) {
                return;
            }

            $origine = CandidateStatut::tryFrom((string) $candidate->getRawOriginal('statut'));

            if ($origine === null || ! $origine->estFinal()) {
                return;
            }

            throw ValidationException::withMessages([
                'statut' => match ($candidate->statut) {
                    CandidateStatut::EntretienPrevu => CycleApprenant::MSG_RETOUR_ENTRETIEN,
                    CandidateStatut::EntretienAPlanifier => CycleApprenant::MSG_RETOUR_A_PLANIFIER,
                    default => 'Décision finale déjà prise : le statut du candidat ne peut plus être modifié.',
                },
            ]);
        });

        // NB : l'acceptation d'un candidat n'ouvre PLUS automatiquement de
        // dossier Matching (choix métier). L'équipe CFA envoie explicitement le
        // candidat accepté vers le Matching via l'action « Envoyer vers
        // Matching » (voir CandidatesTable), quand elle le décide.
    }

    /**
     * Messages métier des retours interdits (le blocage structurel est porté
     * par l'enum ; on remplace seulement le message générique).
     */
    public function transitionBlockReason(HasStateTransitions $to): ?string
    {
        if ($this->statut->estFinal() && in_array($to, CandidateStatut::statutsEntretien(), true)) {
            return $to === CandidateStatut::EntretienPrevu
                ? CycleApprenant::MSG_RETOUR_ENTRETIEN
                : CycleApprenant::MSG_RETOUR_A_PLANIFIER;
        }

        return $this->baseTransitionBlockReason($to);
    }

    /**
     * Gardes métier de la machine à états (cycle apprenant) :
     *  - « Entretien prévu » exige un entretien réellement planifié
     *    (date + heures) dans la section Entretiens ;
     *  - « Accepté » exige un entretien réalisé — un administrateur garde
     *    une action exceptionnelle pour passer outre.
     */
    public function guardTransition(\BackedEnum $from, \BackedEnum $to): ?string
    {
        if ($to === CandidateStatut::EntretienPrevu
            && ! $this->entretiens()->where('statut', EntretienStatut::Planifie->value)->exists()) {
            return CycleApprenant::MSG_ENTRETIEN_NON_PLANIFIE;
        }

        if ($to === CandidateStatut::Accepte
            && ! $this->entretiens()->where('statut', EntretienStatut::Realise->value)->exists()
            && ! (Auth::user()?->hasRole('Administrateur') ?? false)) {
            return CycleApprenant::MSG_ACCEPTATION_SANS_ENTRETIEN;
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
     * Fichier média du CV du candidat (collection « cv » en priorité, sinon
     * document GED de type CV), pour le recopier ailleurs — ex. joindre le CV
     * réellement transmis au dossier Matching. Null si aucun CV.
     */
    public function cvMedia(): ?Media
    {
        if (($media = $this->getFirstMedia('cv')) !== null) {
            return $media;
        }

        return $this->documents()
            ->where('type', DocumentType::CvCandidat->value)
            ->whereHas('media')
            ->latest()
            ->first()
            ?->getFirstMedia('fichier');
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
                'url' => SecureMedia::url($media),
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
            'url' => SecureMedia::url($media),
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

    /** URL de la photo de profil de l'apprenant, ou null si aucune n'est déposée. */
    public function photoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('photo');

        return $url !== '' ? $url : null;
    }

    /** Initiales (avatar sans photo) — ex. « Raslen Saadi » → « RS ». */
    protected function initiales(): Attribute
    {
        return Attribute::get(fn (): string => str(mb_substr($this->prenom ?? '', 0, 1).mb_substr($this->nom ?? '', 0, 1))
            ->upper()->whenEmpty(fn () => str('?'))->value());
    }

    /**
     * Pièces réellement exigées d'un candidat — alignées sur le formulaire de
     * candidature ({@see CandidatureController} :
     * pièce d'identité, CV et carte vitale sont « required »). On ne liste QUE
     * ces pièces demandées (pas de document inventé type diplômes/bulletins).
     * L'attestation de projet (30 ans et +) est stockée en type « Autre »,
     * non distinguable de façon fiable, donc exclue de ce suivi.
     *
     * @return list<DocumentType>
     */
    public static function piecesAttendues(): array
    {
        return [DocumentType::PieceIdentite, DocumentType::CvCandidat, DocumentType::CarteVitale];
    }

    /**
     * Libellés des pièces attendues encore absentes du dossier (« Documents
     * manquants » du panneau Focus).
     *
     * @return list<string>
     */
    public function piecesManquantes(): array
    {
        $presents = $this->documents()->pluck('type')
            ->map(fn ($t): string => $t instanceof \BackedEnum ? $t->value : (string) $t)
            ->all();

        return collect(self::piecesAttendues())
            ->reject(fn (DocumentType $t): bool => in_array($t->value, $presents, true))
            ->map(fn (DocumentType $t): string => $t->getLabel())
            ->values()->all();
    }

    /**
     * Étapes de progression du candidat, chacune avec son état
     * (done / current / todo / refuse). Alimente la colonne « Progression » de la
     * liste et le filtre « Progression » ({@see scopeAEtape}).
     *
     * ⚠️ LIMITE CONNUE (constatée le 2026-07-15, non corrigée — décision
     * utilisateur) : ces 5 étapes ne connaissent NI le contrat, NI l'OPCO, NI la
     * rupture. L'application porte donc deux définitions concurrentes du
     * parcours — celle-ci, et la timeline à 7 étapes de
     * {@see CycleApprenant::etapes()} (qui, elle, couvre contrat,
     * OPCO et rupture). Les deux peuvent se contredire sur un même candidat :
     * un apprenti dont le contrat a été rompu s'affiche ici « Matching », comme
     * s'il cherchait encore une entreprise, faute d'étape le concernant.
     * Correction envisagée : aligner cette colonne sur CycleApprenant::etapes().
     *
     * @return list<array{cle:string,label:string,court:string,etat:string}>
     */
    public function progressionEtapes(): array
    {
        $statut = $this->statut;
        $refuse = $statut === CandidateStatut::Refuse;

        $etapes = [
            ['cle' => 'candidature', 'label' => 'Candidature', 'court' => 'Cand.', 'fait' => true],
            // Un refus clôt la phase d'entretien au même titre qu'une acceptation :
            // sans le compter ici, l'étape « Entretien » raflait l'état « en cours »
            // et l'étape « Accepté » — la seule qui sache afficher un refus — n'était
            // jamais atteinte. Un candidat refusé s'affichait alors comme s'il
            // attendait un entretien.
            ['cle' => 'entretien', 'label' => 'Entretien', 'court' => 'Entr.',
                'fait' => in_array($statut, [
                    CandidateStatut::EntretienRealise,
                    CandidateStatut::Accepte,
                    CandidateStatut::Refuse,
                ], true)],
            ['cle' => 'decision', 'label' => 'Accepté', 'court' => 'Décis.',
                'fait' => $statut === CandidateStatut::Accepte],
            ['cle' => 'matching', 'label' => 'Matching', 'court' => 'Match.',
                'fait' => $this->relationLoaded('matchings')
                    ? $this->matchings->isNotEmpty()
                    : $this->matchings()->exists()],
            ['cle' => 'admission', 'label' => 'Admission', 'court' => 'Adm.',
                'fait' => $this->relationLoaded('admissions')
                    ? $this->admissions->contains(fn ($a): bool => $a->statut === AdmissionStatut::Valide)
                    : $this->admissions()->where('statut', AdmissionStatut::Valide->value)->exists()],
        ];

        $couranteTrouvee = false;
        foreach ($etapes as &$e) {
            if ($e['fait']) {
                $e['etat'] = 'done';
            } elseif (! $couranteTrouvee) {
                $e['etat'] = ($refuse && $e['cle'] === 'decision') ? 'refuse' : 'current';
                $couranteTrouvee = true;
            } else {
                $e['etat'] = 'todo';
            }
            unset($e['fait']);
        }
        unset($e);

        return $etapes;
    }

    /**
     * Libellés des étapes du filtre « Progression ».
     *
     * @return array<string, string>
     */
    public static function etapesProgression(): array
    {
        return [
            'entretien' => 'Entretien à passer',
            'decision' => 'En attente de décision',
            'matching' => 'Cherche une entreprise',
            'admission' => 'En attente d\'admission',
            'termine' => 'Parcours complet',
            'refuse' => 'Refusé',
        ];
    }

    /**
     * Filtre les candidats sur leur étape COURANTE de progression.
     *
     * Miroir SQL de {@see progressionEtapes()} : l'étape courante est la première
     * qui n'est pas franchie. Les deux doivent rester d'accord — un filtre qui
     * contredirait les points affichés serait pire que pas de filtre du tout.
     */
    public function scopeAEtape(Builder $query, string $etape): Builder
    {
        $aUnMatching = fn (Builder $q): Builder => $q->whereHas('matchings');
        $admissionValidee = fn (Builder $q): Builder => $q->whereHas(
            'admissions',
            fn (Builder $a) => $a->where('statut', AdmissionStatut::Valide->value),
        );

        return match ($etape) {
            // Entretien pas encore passé (ni décision rendue).
            'entretien' => $query->whereIn('statut', [
                CandidateStatut::EntretienAPlanifier->value,
                CandidateStatut::EntretienPrevu->value,
            ]),
            // Entretien réalisé, décision (accepté / refusé) pas encore rendue.
            'decision' => $query->where('statut', CandidateStatut::EntretienRealise->value),
            'refuse' => $query->where('statut', CandidateStatut::Refuse->value),
            // Accepté, mais aucune piste entreprise ouverte : le gros du travail commercial.
            'matching' => $query->where('statut', CandidateStatut::Accepte->value)
                ->whereDoesntHave('matchings'),
            // En piste chez une entreprise, admission pas encore validée (elle ne
            // l'est qu'à l'acceptation du financement OPCO).
            'admission' => $query->where('statut', CandidateStatut::Accepte->value)
                ->tap($aUnMatching)
                ->whereDoesntHave('admissions', fn (Builder $a) => $a->where('statut', AdmissionStatut::Valide->value)),
            'termine' => $query->where('statut', CandidateStatut::Accepte->value)
                ->tap($aUnMatching)
                ->tap($admissionValidee),
            default => $query,
        };
    }

    /**
     * Étape courante du parcours pour le panneau Focus — dérivée de la timeline
     * réelle du cycle apprenant ({@see CycleApprenant::etapes()}). Évite les
     * suggestions absurdes (ex. « planifier un entretien » alors que le candidat
     * est déjà accepté et en matching).
     *
     * @return array{cle:string,libelle:string,detail:string,etat:string}
     */
    public function parcoursFocus(): array
    {
        $etapes = app(CycleApprenant::class)->etapes($this);
        $parCle = collect($etapes)->keyBy('cle');

        // Rupture en cours → prioritaire.
        $rupture = $parCle->get('rupture');
        if ($rupture !== null && $rupture['etat'] === CycleApprenant::ETAT_EN_COURS) {
            return ['cle' => 'rupture', 'libelle' => 'Rupture', 'detail' => $rupture['detail'], 'etat' => CycleApprenant::ETAT_EN_COURS];
        }

        if ($this->statut === CandidateStatut::Refuse) {
            return ['cle' => 'refuse', 'libelle' => 'Candidature refusée', 'detail' => 'Aucune action requise.', 'etat' => CycleApprenant::ETAT_BLOQUEE];
        }

        // Étape active = première non terminée du parcours principal.
        foreach (['candidat', 'entretien', 'matching', 'contrat', 'opco', 'admission'] as $cle) {
            $etape = $parCle->get($cle);
            if ($etape !== null && $etape['etat'] !== CycleApprenant::ETAT_TERMINEE) {
                // Les étapes « candidat »/« entretien » se traitent via l'entretien.
                $libelle = in_array($cle, ['candidat', 'entretien'], true) ? 'Entretien' : $etape['libelle'];

                return ['cle' => in_array($cle, ['candidat', 'entretien'], true) ? 'entretien' : $cle,
                    'libelle' => $libelle, 'detail' => $etape['detail'], 'etat' => $etape['etat']];
            }
        }

        return ['cle' => 'complet', 'libelle' => 'Apprenant inscrit', 'detail' => 'Parcours complet.', 'etat' => CycleApprenant::ETAT_TERMINEE];
    }

    /**
     * Résumé de la dernière activité (dernière interaction) : libellé + date.
     *
     * @return array{label:string,quand:?string}|null
     */
    public function derniereActivite(): ?array
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

    public function formationVisee(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'formation_visee_id');
    }

    /** Les classes (matières) suivies — toutes au sein de SA formation. */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class)
            ->using(CandidatePromotion::class)
            ->withPivot(['matieres', 'invitation_token', 'invited_at', 'responded_at'])
            ->withTimestamps();
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    /** Dernière admission officielle du candidat (une par contrat signé). */
    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class)->latestOfMany();
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    /** Entretiens de recrutement du candidat (section Entretiens). */
    public function entretiens(): HasMany
    {
        return $this->hasMany(Entretien::class);
    }

    /** Dernier entretien créé (fiche candidat, timeline). */
    public function dernierEntretien(): HasOne
    {
        return $this->hasOne(Entretien::class)->latestOfMany();
    }

    /**
     * Entretien « en cours » du candidat (à planifier / planifié / à
     * reprogrammer / absent), s'il en existe un. Garantit qu'on ne crée pas
     * de doublon : on reprogramme celui-ci au lieu d'en ouvrir un second.
     */
    public function entretienActif(): ?Entretien
    {
        return $this->entretiens()
            ->whereIn('statut', array_map(fn (EntretienStatut $s) => $s->value, Entretien::ACTIFS))
            ->latest('id')
            ->first();
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

    /** Les notes de l'apprenant (bulletins). */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Moyennes par matière (pondérées par coefficient), sur 20.
     *
     * @return array<int, array{matiere: string, moyenne: float, coefficient: float, nb: int}>
     */
    public function moyennesParMatiere(): array
    {
        return $this->evaluations()
            ->get()
            ->groupBy('matiere')
            ->map(function ($notes, $matiere): array {
                $poids = $notes->sum(fn (Evaluation $e) => (float) $e->coefficient);
                $somme = $notes->sum(fn (Evaluation $e) => $e->noteSur20() * (float) $e->coefficient);

                return [
                    'matiere' => $matiere,
                    'moyenne' => $poids > 0 ? round($somme / $poids, 2) : 0.0,
                    'coefficient' => $poids,
                    'nb' => $notes->count(),
                ];
            })
            ->sortBy('matiere')
            ->values()
            ->all();
    }

    /** Moyenne générale (moyenne des moyennes de matières), sur 20, ou null si aucune note. */
    public function moyenneGenerale(): ?float
    {
        $matieres = $this->moyennesParMatiere();

        if ($matieres === []) {
            return null;
        }

        return round(collect($matieres)->avg('moyenne'), 2);
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
     * Offres (besoins ouverts) proposables à ce candidat au Matching : celles
     * dont la « Formation visée » du besoin correspond à la formation visée du
     * candidat. Si le candidat n'a pas de formation renseignée, toutes les
     * offres ouvertes sont retournées (repli). Alimente « Envoyer vers Matching ».
     *
     * @return Collection<int, Need>
     */
    public function offresProposables(): Collection
    {
        return Need::query()
            ->ouverts()
            ->when(
                $this->formation_visee_id !== null,
                fn ($query) => $query->where('formation_id', $this->formation_visee_id),
            )
            ->with('company')
            ->get();
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
