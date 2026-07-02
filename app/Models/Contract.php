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

class Contract extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

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
            'statut_signature' => ContractSignatureStatut::class,
            'statut_contrat' => ContractStatut::class,
            'risk_level' => \App\Enums\RiskLevel::class,
            'risk_factors' => 'array',
            'risk_evaluated_at' => 'datetime',
        ];
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

    public function financeLines(): HasMany
    {
        return $this->hasMany(FinanceLine::class);
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

        // Transmission OPCO : ouvre le dossier OPCO s'il n'existe pas (P0-08-4).
        if ($to === ContractStatut::TransmisOpco) {
            $this->opcoFile()->firstOrCreate([], ['statut' => OpcoStatut::APreparer->value]);
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire' => $comment])->saveQuietly();
        }
    }
}
