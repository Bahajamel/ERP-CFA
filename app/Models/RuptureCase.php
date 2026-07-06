<?php

namespace App\Models;

use App\Enums\RuptureInitiateur;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\StateMachine\ManagesState;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Dossier de rupture d'un contrat d'apprentissage (EPIC-18).
 *
 * Prend le relais de la détection de risque : une fois la rupture engagée, le
 * dossier trace le motif, pilote l'accompagnement de l'apprenti et sa recherche
 * d'un nouvel employeur (reclassement), et conserve les preuves (documents).
 */
class RuptureCase extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_rupture' => 'date',
            'date_cloture' => 'date',
            'statut' => RuptureStatut::class,
            'motif' => RuptureMotif::class,
            'initiateur' => RuptureInitiateur::class,
            'recherche_employeur' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'motif', 'initiateur', 'recherche_employeur', 'nouvelle_company_id', 'date_cloture'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('rupture');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** Nouvel employeur retrouvé pour l'apprenti (reclassement). */
    public function nouvelleCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'nouvelle_company_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** Preuves du dossier (courrier de rupture, accusé, justificatifs…). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function estClos(): bool
    {
        return $this->statut === RuptureStatut::Clos;
    }

    /**
     * Effets de bord des transitions du dossier : la clôture horodate la
     * fermeture ; le reclassement acte qu'un nouvel employeur a été retrouvé.
     */
    protected function afterTransition(BackedEnum $from, BackedEnum $to, ?string $comment): void
    {
        if ($to === RuptureStatut::Clos && $this->date_cloture === null) {
            $this->forceFill(['date_cloture' => now()->toDateString()])->saveQuietly();
        }

        if ($to === RuptureStatut::EnAccompagnement && ! $this->recherche_employeur) {
            $this->forceFill(['recherche_employeur' => true])->saveQuietly();
        }

        if (filled($comment)) {
            $this->forceFill([
                'accompagnement' => trim(($this->accompagnement ? $this->accompagnement."\n" : '')
                    .now()->format('d/m/Y').' — '.$comment),
            ])->saveQuietly();
        }
    }
}
