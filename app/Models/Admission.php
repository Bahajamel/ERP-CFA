<?php

namespace App\Models;

use App\Enums\AdmissionStatut;
use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Admission extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

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

    /** Effets de bord après une transition : métadonnées de validation + commentaire. */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if ($to === AdmissionStatut::Valide) {
            $this->forceFill([
                'validated_by' => auth()->id(),
                'validated_at' => now(),
            ])->saveQuietly();
        }

        if (filled($comment)) {
            $this->forceFill(['commentaire' => $comment])->saveQuietly();
        }
    }
}
