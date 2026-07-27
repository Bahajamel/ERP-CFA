<?php

namespace App\Models;

use App\Enums\AdmissionStatut;
use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToOrganisation;
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
 * parties → dossier OPCO accepté → admission « À vérifier »). Une fois
 * validée, l'apprenant est officiellement inscrit au CFA avec son contrat
 * et sa convention. La rupture reste possible à tout moment.
 */
class Admission extends Model
{
    use BelongsToOrganisation;
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    /**
     * Invariant backend (cycle apprenant) : une admission officielle ne peut
     * être créée qu'adossée à un contrat signé par les trois parties dont le
     * dossier OPCO est accepté (financement validé) — quel que soit le chemin
     * (formulaire, service, écriture directe).
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
     * Pièces principales du dossier d'admission — les mêmes que celles déposées,
     * obligatoirement, au formulaire de candidature (pièce d'identité, CV, carte
     * vitale / attestation sécurité sociale). Ce sont elles qui alimentent le
     * CERFA et la convention. Tout apprenant présent en admission les possède
     * déjà : une pièce absente ici est une ANOMALIE, jamais un état normal.
     */
    public const PIECES_OBLIGATOIRES = [
        DocumentType::PieceIdentite,
        DocumentType::CvCandidat,
        DocumentType::CarteVitale,
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
     * Pièces obligatoires de l'admission (pièce d'identité, CV, diplôme /
     * bulletins) encore absentes du dossier du candidat — libellés lisibles.
     * S'appuie sur les documents réels du candidat (source fiable), comme le
     * suivi documentaire côté candidat.
     *
     * @return list<string>
     */
    public function piecesAdmissionManquantes(): array
    {
        $candidate = $this->candidate;

        if ($candidate === null) {
            return array_map(fn (DocumentType $t): string => $t->getLabel(), self::PIECES_OBLIGATOIRES);
        }

        $presents = $candidate->documents()->pluck('type')
            ->map(fn ($t): string => $t instanceof BackedEnum ? $t->value : (string) $t)
            ->all();

        return collect(self::PIECES_OBLIGATOIRES)
            ->reject(fn (DocumentType $t): bool => in_array($t->value, $presents, true))
            ->map(fn (DocumentType $t): string => $t->getLabel())
            ->values()->all();
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
