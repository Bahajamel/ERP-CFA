<?php

namespace App\Models;

use App\Enums\CandidateStatut;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Candidate extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'statut' => CandidateStatut::class,
        ];
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
