<?php

namespace App\Models;

use App\Enums\AdmissionStatut;
use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use App\Parcours\CycleApprenant;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Admission officielle de l'apprenant — dernière étape du cycle d'entrée
 * (candidat accepté → entreprise trouvée → contrat signé par les trois
 * parties → dossier OPCO créé/transmis → admission « À vérifier »).
 */
class Admission extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    /**
     * Invariant backend (cycle apprenant) : une admission officielle ne peut
     * être créée qu'adossée à un contrat signé par les trois parties dont le
     * dossier OPCO est créé ou transmis pour validation — quel que soit le
     * chemin (formulaire, service, écriture directe).
     */
    protected static function booted(): void
    {
        static::creating(function (self $admission): void {
            $contract = Contract::query()->find($admission->contract_id);

            if ($contract === null || ! $contract->estSigne()) {
                throw ValidationException::withMessages([
                    'contract_id' => 'Impossible de créer une admission : le contrat n\'est pas signé par les trois parties.',
                ]);
            }

            $opco = $contract->opcoFile;

            if ($opco === null || ! CycleApprenant::opcoOuvreAdmission($opco->statut)) {
                throw ValidationException::withMessages([
                    'contract_id' => CycleApprenant::MSG_OPCO_MANQUANT,
                ]);
            }

            // Le candidat découle toujours du contrat.
            $admission->candidate_id ??= $contract->candidate_id;
        });
    }

    /**
     * Pièces obligatoires générées à l'ouverture d'un dossier d'admission.
     * La validation est bloquée tant que l'une d'elles n'est pas « présente ».
     */
    public const PIECES_OBLIGATOIRES = [
        DocumentType::PieceIdentite,
        DocumentType::CvCandidat,
        DocumentType::DiplomeBulletins,
    ];

    protected function casts(): array
    {
        return [
            'statut' => AdmissionStatut::class,
            'validated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'validated_by', 'validated_at', 'commentaire'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('admission');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** Contrat signé qui fonde l'admission officielle (unique par contrat). */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdmissionChecklistItem::class);
    }

    /** Génère (idempotent) les pièces obligatoires standard du dossier. */
    public function genererChecklistObligatoire(): void
    {
        foreach (self::PIECES_OBLIGATOIRES as $type) {
            $this->items()->firstOrCreate(
                ['document_type' => $type->value],
                ['est_obligatoire' => true, 'statut' => ChecklistItemStatut::Manquante->value],
            );
        }
    }

    /** Pièces obligatoires encore manquantes ou non conformes. */
    public function piecesObligatoiresManquantes(): Collection
    {
        return $this->items()
            ->where('est_obligatoire', true)
            ->where('statut', '!=', ChecklistItemStatut::Presente->value)
            ->get();
    }

    /**
     * Le CV du candidat est-il manquant ? À l'étape de pré-admission, le CV est
     * le seul document requis (porté par le candidat). Détection centralisée.
     */
    public function cvManquant(): bool
    {
        return ! ($this->candidate?->hasCv() ?? false);
    }

    /** Le dossier de pré-admission est complet dès lors que le CV est fourni. */
    public function estComplet(): bool
    {
        return ! $this->cvManquant();
    }

    /**
     * Règle métier (pré-admission) : impossible de valider (« présenter ») le
     * dossier tant que le CV du candidat n'est pas fourni. Contrôle backend —
     * indépendant de l'UI.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === AdmissionStatut::Valide && $this->cvManquant()) {
            return 'Impossible de valider ce dossier : le CV du candidat est manquant.';
        }

        return null;
    }

    /**
     * Effets de bord après une transition : métadonnées de validation,
     * commentaire, et ouverture automatique du dossier de rupture quand
     * l'admission passe en « Rupture » (idempotent : un dossier par contrat).
     */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if ($to === AdmissionStatut::Valide) {
            $this->forceFill([
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ])->saveQuietly();
        }

        if ($to === AdmissionStatut::Rupture && $this->contract_id !== null) {
            app(CycleApprenant::class)->ouvrirRupture($this);
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire' => $comment])->saveQuietly();
        }
    }
}
