<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle d'e-mail (« mail type ») réutilisable : objet + corps avec variables
 * {{clé}} résolues à l'envoi. Cloisonné par CFA. Aujourd'hui utilisé pour écrire
 * à l'entreprise qui a publié une offre (variables ci-dessous).
 *
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property ?int $created_by
 */
class EmailTemplate extends Model
{
    use BelongsToOrganisation;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailTemplate $template): void {
            if (blank($template->created_by)) {
                $template->created_by = auth()->id();
            }
        });
    }

    /**
     * Variables disponibles dans un modèle (clé => description), pour l'aide à la
     * saisie et la résolution à l'envoi (contexte « offre »).
     *
     * @return array<string, string>
     */
    public static function variablesOffre(): array
    {
        return [
            'entreprise' => 'Raison sociale de l\'entreprise',
            'contact' => 'Nom du contact de l\'entreprise',
            'offre' => 'Intitulé du poste',
            'formation' => 'Formation visée',
            'lieu' => 'Lieu de l\'offre',
            'date_demarrage' => 'Date de démarrage',
            'commercial' => 'Votre nom (expéditeur)',
        ];
    }

    /**
     * Valeurs des variables pour une offre donnée (entreprise, contact, poste…),
     * telles qu'injectées dans un « mail type ».
     *
     * @return array<string, string>
     */
    public static function valeursPourOffre(Need $need): array
    {
        $contact = $need->contact
            ?? $need->company?->contactPrincipal->first()
            ?? $need->company?->contacts->first();

        return [
            'entreprise' => (string) ($need->company?->raison_sociale ?? ''),
            'contact' => (string) ($contact?->nom_complet ?? ''),
            'offre' => (string) ($need->intitule_poste ?? ''),
            'formation' => (string) ($need->formation?->libelle ?? ''),
            'lieu' => (string) ($need->localisation ?? ''),
            'date_demarrage' => $need->date_demarrage?->format('d/m/Y') ?? '',
            'commercial' => (string) (auth()->user()?->name ?? ''),
        ];
    }

    /** Rend ce modèle pour une offre : corps avec variables résolues. */
    public function corpsPourOffre(Need $need): string
    {
        return self::remplacer($this->body, self::valeursPourOffre($need));
    }

    /**
     * Remplace les variables {{clé}} (tolère les espaces : {{ clé }}) par leurs
     * valeurs. Les clés inconnues sont laissées vides.
     *
     * @param  array<string, string>  $valeurs
     */
    public static function remplacer(string $texte, array $valeurs): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function (array $m) use ($valeurs): string {
            return (string) ($valeurs[strtolower($m[1])] ?? '');
        }, $texte) ?? $texte;
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
