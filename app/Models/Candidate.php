<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use App\Enums\ChecklistItemStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

class Candidate extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'statut' => CandidateStatut::class,
        ];
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
     * Règle métier (CDC §5 / P0-02-6) : blocage du passage à « Dossier complet »
     * tant qu'une pièce obligatoire de l'admission est manquante ou non conforme.
     */
    public function guardTransition(BackedEnum $from, BackedEnum $to): ?string
    {
        if ($to === CandidateStatut::Complet && $this->hasMissingRequiredPieces()) {
            return 'Dossier incomplet : des pièces obligatoires sont manquantes ou non conformes.';
        }

        return null;
    }

    /** Vrai s'il existe au moins une pièce obligatoire non « présente ». */
    public function hasMissingRequiredPieces(): bool
    {
        return (bool) $this->admission?->items()
            ->where('est_obligatoire', true)
            ->where('statut', '!=', ChecklistItemStatut::Presente->value)
            ->exists();
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

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
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

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
