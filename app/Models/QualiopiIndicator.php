<?php

namespace App\Models;

use App\Enums\QualiopiStatut;
use App\Support\QualiopiCriteres;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Indicateur du Référentiel National Qualité (Qualiopi) et son état de
 * conformité pour l'organisme, avec ses preuves rattachées via la GED.
 * L'historique des modifications est journalisé — utile en audit.
 */
class QualiopiIndicator extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => QualiopiStatut::class,
            'specifique_cfa' => 'boolean',
            'reviewed_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'responsable_id', 'commentaire', 'reviewed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('qualiopi');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    protected function critereLabel(): Attribute
    {
        return Attribute::get(fn () => QualiopiCriteres::label((int) $this->critere));
    }

    /**
     * Taux de conformité global (% d'indicateurs conformes parmi les applicables,
     * c.-à-d. hors « non applicable »). Retourne 0 si aucun indicateur applicable.
     */
    public static function tauxConformite(): int
    {
        $applicables = static::query()
            ->whereIn('statut', QualiopiStatut::applicables())
            ->count();

        if ($applicables === 0) {
            return 0;
        }

        $conformes = static::query()
            ->where('statut', QualiopiStatut::Conforme->value)
            ->count();

        return (int) round($conformes / $applicables * 100);
    }
}
