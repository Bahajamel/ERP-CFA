<?php

namespace App\Models;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Enums\ModaliteSuivi;
use App\Enums\OpcoStatut;
use App\Enums\TypeContrat;
use App\Models\Concerns\BelongsToOrganisation;
use App\Parcours\CycleApprenant;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contract extends Model implements HasMedia
{
    use BelongsToOrganisation;
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use ManagesState;
    use SoftDeletes;

    /** Le CERFA (contrat d'apprentissage) signé, rattaché directement au contrat. */
    public function registerMediaCollections(): void
    {
        // Contient NIR + état civil : disque privé, accès via lien sécurisé signé.
        $this->addMediaCollection('cerfa')->useDisk(config('documents.disque_prive'))->singleFile();
    }

    protected $guarded = [];

    /** Valeurs par défaut d'un nouveau contrat (cohérentes quel que soit le SGBD). */
    protected $attributes = [
        'statut_contrat' => 'en_cours',
        'type_contrat' => 'apprentissage',
    ];

    /**
     * Anti-doublon (cycle apprenant) : un seul contrat actif par couple
     * candidat × entreprise — les contrats rompus ou archivés n'empêchent
     * pas d'en ouvrir un nouveau. Invariant backend, quel que soit le chemin.
     */
    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            $doublon = static::query()
                ->where('candidate_id', $contract->candidate_id)
                ->where('company_id', $contract->company_id)
                ->whereNotIn('statut_contrat', [ContractStatut::Rompu->value])
                ->exists();

            if ($doublon) {
                throw ValidationException::withMessages([
                    'company_id' => 'Un contrat est déjà en cours pour ce candidat et cette entreprise.',
                ]);
            }
        });

        // Cycle apprenant : un contrat qui passe « Complet » (signé) par
        // écriture directe (formulaire, import) ouvre aussi son dossier OPCO —
        // même déclencheur que la machine à états (idempotent).
        static::updated(function (self $contract): void {
            if ($contract->wasChanged('statut_contrat')
                && $contract->statut_contrat === ContractStatut::Complet
                && $contract->estSigne()) {
                $contract->ouvrirDossierOpco();
            }
        });
    }

    /** La machine à états porte sur le statut du contrat. */
    public function stateColumn(): string
    {
        return 'statut_contrat';
    }

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'date_signature' => 'date',
            'duree_hebdo_heures' => 'integer',
            'salaire_mensuel_brut' => 'decimal:2',
            'cout_formation' => 'decimal:2',
            'duree_formation_heures' => 'integer',
            'nombre_organismes_formation' => 'integer',
            'heures_elearning' => 'integer',
            'heures_classe_virtuelle' => 'integer',
            'reste_a_charge_zero' => 'boolean',
            'second_maitre' => 'boolean',
            'regime_assurance_chomage' => 'boolean',
            'financement_cnfpt' => 'boolean',
            'facturation_emails' => 'array',
            // Onglet Contrat — termes du contrat
            'derogation' => 'boolean',
            'duree_hebdo_minutes' => 'integer',
            'avantage_repas' => 'decimal:2',
            'avantage_logement' => 'decimal:2',
            'autres_avantages' => 'boolean',
            'travail_dangereux' => 'boolean',
            // Onglet Contrat — calendrier & rémunération
            'date_debut_contrat' => 'date',
            'date_fin_contrat' => 'date',
            'date_fin_periode_essai' => 'date',
            'date_conclusion' => 'date',
            'date_debut_formation_pratique' => 'date',
            'smc' => 'boolean',
            'pourcentage_smic' => 'decimal:2',
            'remuneration_annuelle' => 'array',
            // Onglet Contrat — données financières / OPCO
            'npec_annuel' => 'decimal:2',
            'npec_journalier' => 'decimal:2',
            'nombre_jours_contrat' => 'integer',
            'engagement_opco_total' => 'decimal:2',
            // Onglet Contrat — reste à charge entreprise
            'reste_a_charge_montant' => 'decimal:2',
            'participation_obligatoire' => 'decimal:2',
            'participation_cfa' => 'decimal:2',
            'net_a_payer' => 'decimal:2',
            'calendrier_financement' => 'array',
            // Onglet Contrat — frais annexes
            'frais_hebergement' => 'boolean',
            'frais_restauration' => 'boolean',
            'frais_equipement' => 'boolean',
            'frais_mobilite' => 'boolean',
            // Onglet Gestion — marqueurs et réglages
            'non_conforme' => 'boolean',
            'non_conforme_at' => 'datetime',
            'annule_at' => 'datetime',
            'relances_activees' => 'boolean',
            'facturation_opco' => 'boolean',
            'lieu_formation_latitude' => 'decimal:7',
            'lieu_formation_longitude' => 'decimal:7',
            'modalite_suivi' => ModaliteSuivi::class,
            'type_contrat' => TypeContrat::class,
            'statut_signature' => ContractSignatureStatut::class,
            'statut_contrat' => ContractStatut::class,
        ];
    }

    /** Le dossier est-il déclaré non conforme (marqueur de gestion) ? */
    public function estNonConforme(): bool
    {
        return (bool) $this->non_conforme;
    }

    /** Le dossier est-il annulé (annulation logique, reste consultable) ? */
    public function estAnnule(): bool
    {
        return $this->annule_at !== null;
    }

    /**
     * Statut de relecture des documents (badge de l'onglet Gestion), déduit de
     * l'état du dossier — sans nouvelle colonne de statut.
     *
     * @return array{label: string, color: string}
     */
    public function relectureStatut(): array
    {
        if ($this->estAnnule()) {
            return ['label' => 'Annulé', 'color' => 'danger'];
        }

        if ($this->estNonConforme()) {
            return ['label' => 'Non conforme', 'color' => 'danger'];
        }

        if (in_array($this->statut_contrat, [ContractStatut::Complet, ContractStatut::ACorriger], true)) {
            return ['label' => 'Validé', 'color' => 'success'];
        }

        if ($this->aDocumentContractuel()) {
            return ['label' => 'À vérifier', 'color' => 'warning'];
        }

        return ['label' => 'En cours', 'color' => 'info'];
    }

    /**
     * Date de début effective du contrat : la date de début du CONTRAT (onglet
     * Contrat) si renseignée, sinon la date de début de FORMATION (onglet
     * Étudiant) — repli pour les anciens dossiers. Utilisée par le CERFA, la
     * convention et le calcul de rémunération.
     */
    public function dateDebutEffective(): mixed
    {
        return $this->date_debut_contrat ?? $this->date_debut;
    }

    /** Date de fin effective du contrat (contrat, sinon formation). */
    public function dateFinEffective(): mixed
    {
        return $this->date_fin_contrat ?? $this->date_fin;
    }

    /**
     * Lieu principal de formation, sur une ligne lisible (voie, CP ville) —
     * utilisé par la convention et le CERFA. Null si rien n'est renseigné.
     */
    public function lieuFormationLisible(): ?string
    {
        $ligne = trim(implode(' ', array_filter([
            $this->lieu_formation_code_postal,
            $this->lieu_formation_ville,
        ])));

        $complet = trim(implode(', ', array_filter([
            $this->lieu_formation,
            $ligne !== '' ? $ligne : null,
        ])));

        return $complet !== '' ? $complet : null;
    }

    /**
     * Le contrat est-il signé (dossier complet et signé) ? Condition de génération
     * des livrables : un contrat signé implique un dossier complet et validé.
     */
    public function estSigne(): bool
    {
        return $this->statut_signature === ContractSignatureStatut::Signe
            || in_array($this->statut_contrat, [
                ContractStatut::Complet,
                // « À corriger » = signé puis renvoyé par l'OPCO : reste un contrat signé.
                ContractStatut::ACorriger,
            ], true);
    }

    /**
     * Ouvre le dossier OPCO du contrat s'il n'existe pas encore, pour lancer le
     * suivi du financement et des paiements. Idempotent (firstOrCreate).
     */
    public function ouvrirDossierOpco(): void
    {
        $dossier = $this->opcoFile()->firstOrCreate([], [
            'statut' => OpcoStatut::APreparer->value,
            // OPCO pré-rempli depuis l'entreprise (déduit du SIRET) : pas de ressaisie.
            'opco_id' => $this->company?->opco_id,
        ]);

        if ($dossier->wasRecentlyCreated) {
            CycleApprenant::notifierAutomatisme(
                'Contrat signé',
                'Dossier OPCO créé automatiquement (« À préparer ») pour '
                .($this->candidate?->nom_complet ?? 'l\'apprenant').'.',
            );
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut_contrat', 'statut_signature', 'candidate_id', 'company_id', 'date_debut', 'date_fin'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('contrat');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /** Promotion (session de formation) rattachée au contrat. */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** Responsable pédagogique de la formation (utilisateur du CFA). */
    public function responsablePedagogique(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_pedagogique_id');
    }

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'tuteur_id');
    }

    /** Responsable interne du dossier (utilisateur du CFA). */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Second maître d'apprentissage (contact de l'entreprise), s'il existe. */
    public function tuteur2(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'tuteur2_id');
    }

    public function opcoFile(): HasOne
    {
        return $this->hasOne(OpcoFile::class);
    }

    /** Admission officielle fondée sur ce contrat (une au plus). */
    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class);
    }

    /** Demandes de signature électronique multi-parties (EPIC-08). */
    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class);
    }

    public function financeLines(): HasMany
    {
        return $this->hasMany(FinanceLine::class);
    }

    public function rupture(): HasOne
    {
        return $this->hasOne(Rupture::class);
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Types de documents faisant foi d'un contrat signé. */
    public const TYPES_CONTRACTUELS = [
        DocumentType::Contrat,
        DocumentType::Cerfa,
        DocumentType::Convention,
    ];

    /** Un document contractuel (contrat / CERFA / convention) est-il associé ? */
    public function aDocumentContractuel(): bool
    {
        return $this->documents()
            ->whereIn('type', array_map(fn (DocumentType $t) => $t->value, self::TYPES_CONTRACTUELS))
            ->exists();
    }

    /**
     * Règle métier (CDC P0-08-5) : pas de passage à « Signé » sans preuve —
     * soit un document contractuel associé, soit la signature marquée comme
     * signée (justification manuelle / signature électronique).
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === ContractStatut::Complet
            && $this->statut_signature !== ContractSignatureStatut::Signe
            && ! $this->aDocumentContractuel()) {
            return 'Passage à « Complet » impossible : associez un document contractuel signé '
                .'(contrat / CERFA / convention) ou marquez la signature comme signée.';
        }

        return null;
    }

    /** Effets de bord des transitions : signature, dossier OPCO, commentaire. */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        // Cohérence : atteindre « Complet » fixe le statut de signature.
        if ($to === ContractStatut::Complet && $this->statut_signature !== ContractSignatureStatut::Signe) {
            $this->forceFill(['statut_signature' => ContractSignatureStatut::Signe])->saveQuietly();
        }

        // « Complet » (signé) → ouverture automatique du dossier OPCO pour
        // lancer le suivi du financement et des paiements.
        if ($to === ContractStatut::Complet) {
            $this->ouvrirDossierOpco();
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire' => $comment])->saveQuietly();
        }
    }
}
