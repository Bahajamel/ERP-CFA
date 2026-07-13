<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Profil du CFA (singleton) : identité, référents, préférences de génération,
 * et pièces graphiques (logo, signature, cachet) appliquées aux livrables
 * générés par LivretRS. Un seul enregistrement — accès via CfaProfile::current().
 */
class CfaProfile extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verifier_rncp' => 'boolean',
        ];
    }

    /** Le profil unique du CFA (créé à la volée s'il n'existe pas). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['nom' => config('cfa.nom', 'CFA')]);
    }

    public function registerMediaCollections(): void
    {
        // Logo : figure sur les documents générés → reste public.
        $this->addMediaCollection('logo')->singleFile();

        // Signature et cachet du CFA : détournables (risque de falsification) →
        // disque privé. Utilisés côté serveur (getPath) pour la génération PDF.
        $disquePrive = config('documents.disque_prive');
        $this->addMediaCollection('signature')->useDisk($disquePrive)->singleFile();
        $this->addMediaCollection('cachet')->useDisk($disquePrive)->singleFile();
    }
}
