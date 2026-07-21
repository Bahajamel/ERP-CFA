<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Question / réponse d'un {@see FaqBot}. Modifiable depuis l'administration,
 * sans toucher au code.
 *
 * @property string $question
 * @property string $answer
 * @property ?array $keywords
 * @property ?string $category
 * @property bool $is_active
 */
class FaqEntry extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Une entrée appartient forcément au même CFA que son assistant.
        static::creating(function (FaqEntry $entree): void {
            if (blank($entree->organisation_id) && $entree->faqBot !== null) {
                $entree->organisation_id = $entree->faqBot->organisation_id;
            }
        });
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function faqBot(): BelongsTo
    {
        return $this->belongsTo(FaqBot::class);
    }

    /**
     * Lien vers la page concernée, ou null (lien absent, ou permission
     * manquante — la réponse texte reste affichée dans tous les cas).
     */
    public function lien(): ?array
    {
        if (blank($this->link_route) || blank($this->link_label)) {
            return null;
        }

        if (filled($this->link_permission) && ! (auth()->user()?->can($this->link_permission) ?? false)) {
            return null;
        }

        return ['route' => $this->link_route, 'label' => $this->link_label];
    }
}
