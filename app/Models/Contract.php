<?php

namespace App\Models;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut_signature' => ContractSignatureStatut::class,
            'statut_contrat' => ContractStatut::class,
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
