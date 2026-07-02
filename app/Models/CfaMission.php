<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mission du CFA au sens de l'article L6231-2 du Code du travail (14 missions).
 * Référentiel figé, seedé depuis le texte officiel ; sert de socle à la
 * couverture documentaire (quels livrables prouvent quelles missions).
 */
class CfaMission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
        ];
    }

    /** Documents (livrables, preuves) rattachés à cette mission. */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'cfa_mission_document')
            ->withTimestamps();
    }

    /** Vrai dès qu'au moins un document couvre la mission. */
    public function estCouverte(): bool
    {
        return $this->documents()->exists();
    }

    /**
     * Taux de couverture global : part des 14 missions couvertes par au moins
     * un document. Renvoie un entier 0-100.
     */
    public static function tauxCouverture(): int
    {
        $total = static::query()->count();

        if ($total === 0) {
            return 0;
        }

        $couvertes = static::query()->has('documents')->count();

        return (int) round($couvertes * 100 / $total);
    }
}
