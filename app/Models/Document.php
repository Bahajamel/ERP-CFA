<?php

namespace App\Models;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Types dont la suppression définitive est interdite (traçabilité légale /
     * financement). On ne peut que les archiver (soft delete).
     */
    public const TYPES_CRITIQUES = [
        DocumentType::Contrat,
        DocumentType::Cerfa,
        DocumentType::Convention,
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'source' => DocumentSource::class,
            'statut' => DocumentStatut::class,
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Anti-suppression sans trace : un document critique ne peut pas être
        // supprimé définitivement, seulement archivé (soft delete).
        static::deleting(function (Document $document) {
            if ($document->isForceDeleting() && $document->estCritique()) {
                throw new RuntimeException(
                    "Suppression définitive interdite pour un document critique ({$document->type->getLabel()})."
                );
            }
        });
    }

    public function registerMediaCollections(): void
    {
        // GED : CERFA, conventions, factures, feuilles d'émargement… → données
        // personnelles/contractuelles. Disque privé, accès via lien sécurisé signé.
        $this->addMediaCollection('fichier')->useDisk(config('documents.disque_prive'))->singleFile();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'statut', 'version', 'previous_version_id', 'documentable_type', 'documentable_id', 'uploaded_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('document');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Missions CFA (L6231-2) prouvées par ce document. */
    public function missions(): BelongsToMany
    {
        return $this->belongsToMany(CfaMission::class, 'cfa_mission_document')
            ->withTimestamps();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'previous_version_id');
    }

    public function nextVersion(): HasOne
    {
        return $this->hasOne(Document::class, 'previous_version_id');
    }

    /** Ne garde que les versions courantes (non remplacées par une version ultérieure). */
    public function scopeVersionsCourantes(Builder $query): Builder
    {
        return $query->whereNotIn('id', function ($sub) {
            $sub->select('previous_version_id')
                ->from('documents')
                ->whereNotNull('previous_version_id');
        });
    }

    public function estCritique(): bool
    {
        return in_array($this->type, self::TYPES_CRITIQUES, true);
    }

    /** Vrai si aucune version plus récente ne remplace ce document. */
    public function estCourante(): bool
    {
        return ! $this->nextVersion()->exists();
    }

    /**
     * Crée une nouvelle version de ce document (chaînée via previous_version_id).
     * Ne gère que la partie base ; le fichier est attaché ensuite par l'appelant.
     */
    public function creerNouvelleVersion(array $attributes = []): static
    {
        $nouvelle = $this->replicate(['deleted_at']);
        $nouvelle->version = $this->version + 1;
        $nouvelle->previous_version_id = $this->id;
        $nouvelle->fill($attributes);
        $nouvelle->save();

        return $nouvelle;
    }

    /** Chaîne complète des versions, de la plus récente à la plus ancienne. */
    public function historiqueVersions(): array
    {
        $versions = [];
        $courant = $this;

        while ($courant !== null) {
            $versions[] = $courant;
            $courant = $courant->previousVersion;
        }

        return $versions;
    }
}
