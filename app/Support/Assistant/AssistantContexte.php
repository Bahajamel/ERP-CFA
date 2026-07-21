<?php

namespace App\Support\Assistant;

use App\Filament\Pages\Assiduite;
use App\Filament\Pages\EmploiDuTemps;
use App\Filament\Pages\Finance;
use App\Filament\Pages\Notes;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Corbeille\CorbeilleResource;
use App\Filament\Resources\Entretiens\EntretienResource;
use App\Filament\Resources\Formations\FormationResource;
use App\Filament\Resources\Matchings\MatchingResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Ruptures\RuptureResource;
use App\Filament\Resources\Seances\SeanceResource;
use App\Models\FaqBot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Détermine QUEL assistant FAQ afficher, selon la page consultée.
 *
 * Le découpage suit les grandes parties du logiciel, telles qu'elles existent
 * déjà dans la navigation — on ne réinvente pas une organisation parallèle :
 *
 *   commercial → Candidats, Entreprises, Offres, Matching, Entretiens, Admission
 *   contrats   → Contrats, Dossiers OPCO, Ruptures
 *   finance    → Finance & Facturation
 *   scolarite  → Formations, Promotions, Séances, Notes, Assiduité, Emploi du temps
 *   pilotage   → tout le reste (Tâches, Documents, Qualité, Administration, accueil)
 *
 * « pilotage » sert de filet : aucune page ne se retrouve sans aide.
 */
class AssistantContexte
{
    public const MODULE_PAR_DEFAUT = 'pilotage';

    /**
     * Définition des assistants : périmètre (ressources/pages) et permission
     * requise pour les voir. Une permission nulle = visible par tous.
     *
     * @return array<string, array{label: string, permission: ?string, resources: list<class-string>, pages: list<class-string>}>
     */
    public static function modules(): array
    {
        return [
            'commercial' => [
                'label' => 'Commercial',
                'permission' => 'access_candidates',
                'resources' => [
                    CandidateResource::class,
                    CompanyResource::class,
                    NeedResource::class,
                    MatchingResource::class,
                    EntretienResource::class,
                    AdmissionResource::class,
                    CorbeilleResource::class,
                ],
                'pages' => [],
            ],
            'contrats' => [
                'label' => 'Contrats & OPCO',
                'permission' => 'access_contracts',
                'resources' => [
                    ContractResource::class,
                    OpcoFileResource::class,
                    RuptureResource::class,
                ],
                'pages' => [],
            ],
            'finance' => [
                'label' => 'Finance & Facturation',
                'permission' => 'access_finance',
                'resources' => [],
                'pages' => [Finance::class],
            ],
            'scolarite' => [
                'label' => 'Scolarité',
                'permission' => 'access_formations',
                'resources' => [
                    FormationResource::class,
                    PromotionResource::class,
                    SeanceResource::class,
                ],
                'pages' => [Assiduite::class, Notes::class, EmploiDuTemps::class],
            ],
            self::MODULE_PAR_DEFAUT => [
                'label' => 'Pilotage & Administration',
                'permission' => null, // filet : jamais de page sans aide
                'resources' => [],
                'pages' => [],
            ],
        ];
    }

    /** Libellés des parties, pour les listes de l'administration. */
    public static function options(): array
    {
        return collect(self::modules())->map(fn (array $m): string => $m['label'])->all();
    }

    /**
     * Module correspondant à la page courante. Retombe sur « pilotage » si la
     * page n'appartient à aucun périmètre déclaré.
     */
    public static function moduleCourant(?string $routeName = null): string
    {
        $routeName ??= request()->route()?->getName();

        if ($routeName === null) {
            return self::MODULE_PAR_DEFAUT;
        }

        foreach (self::modules() as $module => $config) {
            foreach ($config['resources'] as $resource) {
                if (self::correspond(self::nomDeRoute($resource, 'resource'), $routeName)) {
                    return $module;
                }
            }

            foreach ($config['pages'] as $page) {
                if (self::correspond(self::nomDeRoute($page, 'page'), $routeName)) {
                    return $module;
                }
            }
        }

        return self::MODULE_PAR_DEFAUT;
    }

    /**
     * Assistant à afficher : celui du module courant s'il est actif ET que
     * l'utilisateur a accès à cette partie ; sinon l'assistant de repli.
     */
    public static function botCourant(?string $routeName = null): ?FaqBot
    {
        return self::botPourModule(self::moduleCourant($routeName));
    }

    /**
     * Assistant d'un module donné, une fois les permissions vérifiées : si la
     * partie est interdite à l'utilisateur, il obtient l'assistant de repli
     * plutôt que rien du tout.
     */
    public static function botPourModule(string $module): ?FaqBot
    {
        if (! self::peutVoir($module)) {
            $module = self::MODULE_PAR_DEFAUT;
        }

        return FaqBot::query()->actif()->where('module', $module)->first()
            ?? FaqBot::query()->actif()->where('module', self::MODULE_PAR_DEFAUT)->first();
    }

    /** L'utilisateur a-t-il accès à cette partie du logiciel ? */
    public static function peutVoir(string $module): bool
    {
        $permission = self::modules()[$module]['permission'] ?? null;

        return $permission === null || (Auth::user()?->can($permission) ?? false);
    }

    /** Nom de route de base d'une ressource / d'une page, ou null si indisponible. */
    private static function nomDeRoute(string $classe, string $type): ?string
    {
        try {
            return $type === 'resource'
                ? $classe::getRouteBaseName()
                : $classe::getRouteName();
        } catch (Throwable) {
            // Hors contexte panel (console, tests unitaires) : on ignore.
            return null;
        }
    }

    /** La route courante relève-t-elle de ce nom de base ? (préfixe Filament) */
    private static function correspond(?string $base, string $routeName): bool
    {
        if ($base === null) {
            return false;
        }

        return $routeName === $base || Str::startsWith($routeName, $base.'.');
    }
}
