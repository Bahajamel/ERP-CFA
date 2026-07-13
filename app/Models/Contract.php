<?php

namespace App\Models;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Enums\OpcoStatut;
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

    /** Statut par défaut d'un nouveau contrat (cohérent quel que soit le SGBD). */
    protected $attributes = [
        'statut_contrat' => 'en_cours',
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
            'salaire_mensuel_brut' => 'decimal:2',
            'cout_formation' => 'decimal:2',
            'duree_formation_heures' => 'integer',
            'lieu_formation_latitude' => 'decimal:7',
            'lieu_formation_longitude' => 'decimal:7',
            'statut_signature' => ContractSignatureStatut::class,
            'statut_contrat' => ContractStatut::class,
        ];
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

    public function tuteur(): BelongsTo
    {
        return $this->belongsTo(CompanyContact::class, 'tuteur_id');
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
