<?php

namespace App\Models;

use App\Enums\SignatureRequestStatut;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Demande de signature électronique multi-parties d'un contrat (EPIC-08).
 *
 * La liste des signataires est portée en JSON (role, nom, email, ordre,
 * signe_at) : elle reflète l'enveloppe envoyée au prestataire eIDAS et se met à
 * jour au fil des signatures (via le webhook du prestataire, ou la simulation).
 */
class SignatureRequest extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statut' => SignatureRequestStatut::class,
            'signataires' => 'array',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'provider', 'external_id', 'sent_at', 'completed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('signature');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function signatairesCollection(): Collection
    {
        return collect($this->signataires ?? []);
    }

    public function nombreSignes(): int
    {
        return $this->signatairesCollection()->whereNotNull('signe_at')->count();
    }

    public function nombreSignataires(): int
    {
        return $this->signatairesCollection()->count();
    }

    public function tousSignes(): bool
    {
        $total = $this->nombreSignataires();

        return $total > 0 && $this->nombreSignes() === $total;
    }

    /** Libellé de progression « 2 / 4 signé(s) ». */
    public function progression(): string
    {
        return $this->nombreSignes().' / '.$this->nombreSignataires().' signé(s)';
    }
}
