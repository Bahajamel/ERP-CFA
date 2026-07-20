<?php

namespace App\Support;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Corbeille\CorbeilleResource;
use App\Filament\Resources\Entretiens\EntretienResource;
use App\Filament\Resources\Matchings\MatchingResource;
use App\Filament\Resources\Needs\NeedResource;
use Illuminate\Support\Str;

/**
 * Barre de navigation contextuelle « Commercial » : configuration centralisée des
 * trois accès rapides (Candidats, Entreprises, Offres) + logique d'affichage et
 * d'onglet actif, déduite de la ROUTE courante (aucun état codé en dur).
 *
 * Injectée une seule fois via un render hook Filament (CONTENT_START), la barre
 * apparaît sur toutes les pages du groupe « Commercial » — sans duplication par
 * page — et met en évidence l'onglet de la ressource consultée, y compris ses
 * sous-pages (index/create/edit/view + relations), par PRÉFIXE de nom de route.
 */
class CommercialNavigation
{
    /**
     * Les 3 bases principales de la barre. On réutilise les ressources Filament
     * existantes : leurs URL (getUrl) et leur périmètre de route (getRouteBaseName)
     * évitent toute route codée en dur, et leur icône de navigation est reprise.
     *
     * @return list<array{key: string, label: string, icon: string, resource: class-string}>
     */
    public static function tabs(): array
    {
        return [
            ['key' => 'candidates', 'label' => 'Candidats', 'icon' => 'heroicon-o-user-group', 'resource' => CandidateResource::class],
            ['key' => 'companies', 'label' => 'Entreprises', 'icon' => 'heroicon-o-building-office-2', 'resource' => CompanyResource::class],
            ['key' => 'offers', 'label' => 'Offres', 'icon' => 'heroicon-o-briefcase', 'resource' => NeedResource::class],
        ];
    }

    /**
     * Ressources du groupe « Commercial » où la barre est VISIBLE : les 3 onglets
     * + Entretiens, Matching, Corbeille (visibles, mais sans onglet actif — sauf
     * si la page relève clairement de l'un des trois domaines).
     *
     * @return list<class-string>
     */
    public static function resourcesCommercial(): array
    {
        return [
            CandidateResource::class,
            CompanyResource::class,
            NeedResource::class,
            EntretienResource::class,
            MatchingResource::class,
            CorbeilleResource::class,
        ];
    }

    /** La barre doit-elle s'afficher sur la page courante ? */
    public static function doitAfficher(): bool
    {
        return self::doitAfficherPour(request()->route()?->getName());
    }

    /** Variante testable : la barre s'affiche-t-elle pour ce nom de route ? */
    public static function doitAfficherPour(?string $routeName): bool
    {
        foreach (self::resourcesCommercial() as $resource) {
            if (self::correspond($resource, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /** L'onglet d'une ressource est-il actif sur la page courante ? */
    public static function estActif(string $resource): bool
    {
        return self::correspond($resource, request()->route()?->getName());
    }

    /** Variante testable de {@see estActif()}. */
    public static function estActifPour(string $resource, ?string $routeName): bool
    {
        return self::correspond($resource, $routeName);
    }

    /** URL de la page principale (index) d'une ressource, tenant courant inclus. */
    public static function url(string $resource): string
    {
        return $resource::getUrl('index');
    }

    /**
     * Une route appartient-elle au périmètre d'une ressource ? Détection par
     * PRÉFIXE du nom de route Filament (ex. « filament.admin.resources.candidates »),
     * ce qui couvre nativement toutes les sous-pages (index/create/edit/view/…)
     * sans les énumérer.
     */
    private static function correspond(string $resource, ?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        $base = $resource::getRouteBaseName();

        return $routeName === $base || Str::startsWith($routeName, $base.'.');
    }
}
