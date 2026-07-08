<?php

namespace App\Models;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Enums\OpcoStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        $this->addMediaCollection('cerfa')->singleFile();
    }

    protected $guarded = [];

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
                ->whereNotIn('statut_contrat', [ContractStatut::Rompu->value, ContractStatut::Archive->value])
                ->exists();

            if ($doublon) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'company_id' => 'Un contrat est déjà en cours pour ce candidat et cette entreprise.',
                ]);
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
            'statut_signature' => ContractSignatureStatut::class,
            'statut_contrat' => ContractStatut::class,
        ];
    }

    /**
     * Le contrat est-il signé (dossier complet et signé) ? Condition de génération
     * des livrables : un contrat signé implique un dossier complet et validé.
     */
    public function estSigne(): bool
    {
        return $this->statut_signature === ContractSignatureStatut::Signe
            || in_array($this->statut_contrat, [
                ContractStatut::Signe,
                ContractStatut::TransmisOpco,
                ContractStatut::Actif,
            ], true);
    }

    /**
     * Ouvre le dossier OPCO du contrat s'il n'existe pas encore, pour lancer le
     * suivi du financement et des paiements. Idempotent (firstOrCreate).
     */
    public function ouvrirDossierOpco(): void
    {
        $this->opcoFile()->firstOrCreate([], ['statut' => OpcoStatut::APreparer->value]);
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
        if ($to === ContractStatut::Signe
            && $this->statut_signature !== ContractSignatureStatut::Signe
            && ! $this->aDocumentContractuel()) {
            return 'Passage à « Signé » impossible : associez un document contractuel signé '
                .'(contrat / CERFA / convention) ou marquez la signature comme signée.';
        }

        return null;
    }

    /** Effets de bord des transitions : signature, dossier OPCO, commentaire. */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        // Cohérence : atteindre « Signé » fixe le statut de signature.
        if ($to === ContractStatut::Signe && $this->statut_signature !== ContractSignatureStatut::Signe) {
            $this->forceFill(['statut_signature' => ContractSignatureStatut::Signe])->saveQuietly();
        }

        // Dès la signature (et à la transmission), on ouvre automatiquement le
        // dossier OPCO pour lancer le suivi du financement et des paiements.
        if (in_array($to, [ContractStatut::Signe, ContractStatut::TransmisOpco], true)) {
            $this->ouvrirDossierOpco();
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire' => $comment])->saveQuietly();
        }
    }
}
