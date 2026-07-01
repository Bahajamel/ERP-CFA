<?php

namespace App\Models;

use App\Enums\OpcoStatut;
use App\StateMachine\ManagesState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OpcoFile extends Model
{
    use HasFactory;
    use LogsActivity;
    use ManagesState;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_depot' => 'date',
            'date_relance' => 'date',
            'montant_prevu' => 'decimal:2',
            'montant_accepte' => 'decimal:2',
            'statut' => OpcoStatut::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'montant_prevu', 'montant_accepte', 'motif_rejet'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('opco');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function opco(): BelongsTo
    {
        return $this->belongsTo(Opco::class);
    }

    public function responsableCorrection(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_correction_id');
    }
}
