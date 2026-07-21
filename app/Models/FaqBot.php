<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Assistant FAQ d'une grande partie du logiciel (Commercial, Contrats & OPCO,
 * Finance, Scolarité, Pilotage & Administration). Porte son identité visuelle
 * et ses questions/réponses. Cloisonné par CFA.
 *
 * @property string $module
 * @property string $name
 * @property ?string $description
 * @property ?string $icon
 * @property string $color
 * @property ?string $avatar_path
 * @property string $welcome_message
 * @property bool $is_active
 */
class FaqBot extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    /** Disque de stockage des avatars téléversés. */
    public const DISQUE_AVATARS = 'public';

    public const DOSSIER_AVATARS = 'assistants/avatars';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Questions/réponses actives, dans l'ordre défini par l'administrateur. */
    public function entrees(): HasMany
    {
        return $this->hasMany(FaqEntry::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Toutes les questions/réponses (y compris désactivées) — pour l'admin. */
    public function toutesLesEntrees(): HasMany
    {
        return $this->hasMany(FaqEntry::class);
    }

    /** URL de l'avatar téléversé, ou null (l'icône prend alors le relais). */
    public function avatarUrl(): ?string
    {
        return filled($this->avatar_path)
            ? Storage::disk(self::DISQUE_AVATARS)->url($this->avatar_path)
            : null;
    }

    /** Icône affichée à défaut d'avatar. */
    public function icone(): string
    {
        return $this->icon ?: 'heroicon-o-sparkles';
    }
}
