<?php

namespace App\Models;

use App\Enums\PresenceStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Présence d'un apprenti à une séance (émargement, EPIC-14). Un justificatif
 * d'absence peut être rattaché (collection média « justificatif », P1-14-3).
 */
class Presence extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => PresenceStatut::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('justificatif')->singleFile();
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** Un justificatif d'absence est-il joint ? */
    public function aJustificatif(): bool
    {
        return $this->getFirstMedia('justificatif') !== null;
    }
}
