<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Attestation mensuelle de service fait (EPIC-15) : figée à la validation, elle
 * atteste l'assiduité d'une promotion sur un mois (base facturation OPCO / preuves).
 */
class ServiceFait extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'mois' => 'integer',
            'nb_seances' => 'integer',
            'nb_heures' => 'float',
            'taux_presence' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** Preuve(s) de service fait (GED polymorphe). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Libellé de la période, ex. « Septembre 2026 ». */
    public function periodeLibelle(): string
    {
        return ucfirst(Carbon::create($this->annee, $this->mois, 1)->locale('fr')->monthName).' '.$this->annee;
    }
}
