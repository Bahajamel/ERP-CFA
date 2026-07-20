<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tableau personnalisé d'un CFA (couche « façon Monday »). Porte un nom, un slug
 * lisible, une description, une icône, une couleur et un état d'activation ; ses
 * colonnes sont des {@see CustomFieldDefinition} (rattachées via custom_table_id)
 * et ses lignes des {@see CustomRecord}. Cloisonné par CFA, archivable (soft delete).
 *
 * @property string $name
 * @property ?string $slug
 * @property ?string $description
 * @property ?string $icon
 * @property ?string $color
 * @property bool $is_active
 * @property ?int $created_by
 * @property ?string $public_token
 */
class CustomTable extends Model
{
    use BelongsToOrganisation;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('table_personnalisee');
    }

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Slug lisible et unique par CFA + auteur + jeton du lien public, à la création.
        static::creating(function (CustomTable $table): void {
            if (blank($table->slug)) {
                $table->slug = self::slugUnique($table->name, $table->organisation_id);
            }

            if (blank($table->created_by)) {
                $table->created_by = auth()->id();
            }

            if (blank($table->public_token)) {
                $table->public_token = Str::random(48);
            }
        });
    }

    /** Génère un slug unique dans le périmètre d'un CFA (unicité garantie en code). */
    public static function slugUnique(?string $nom, ?int $organisationId, ?int $ignorerId = null): string
    {
        $base = Str::slug((string) $nom) ?: 'table';
        $slug = $base;
        $i = 2;

        $existe = fn (string $s): bool => self::query()
            ->withTrashed()
            ->when($organisationId !== null, fn (Builder $q) => $q->where('organisation_id', $organisationId))
            ->where('slug', $s)
            ->when($ignorerId !== null, fn (Builder $q) => $q->whereKeyNot($ignorerId))
            ->exists();

        while ($existe($slug)) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /** Tables actives uniquement (non archivées via is_active). */
    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Colonnes du tableau (définitions rattachées), ordonnées. */
    public function colonnes(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class)->orderBy('sort')->orderBy('id');
    }

    /** Lignes du tableau. */
    public function records(): HasMany
    {
        return $this->hasMany(CustomRecord::class);
    }

    /** Vues enregistrées (agencements nommés) de ce tableau. */
    public function views(): HasMany
    {
        return $this->hasMany(CustomView::class);
    }

    /** Auteur de la création. */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** URL publique du formulaire de candidature qui alimente CE tableau. */
    public function lienCandidature(): string
    {
        if (blank($this->public_token)) {
            $this->forceFill(['public_token' => Str::random(48)])->save();
        }

        return route('tableau.candidature', ['token' => $this->public_token]);
    }
}
