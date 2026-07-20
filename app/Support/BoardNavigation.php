<?php

namespace App\Support;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CustomTable;
use App\Models\Need;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Sélecteur de « boards » façon Monday, généralisé par MODULE : chaque contexte
 * (Candidats, Entreprises, Offres…) réunit son board principal ET les tableaux
 * personnalisés que le CFA a créés POUR ce module. Un CFA peut donc créer autant
 * de tableaux qu'il veut, par module.
 *
 * La barre est injectée une seule fois via un render hook. Le contexte courant et
 * l'onglet actif sont déduits de la route/record (aucun état codé en dur), et tout
 * est cloisonné par CFA + soumis aux permissions.
 */
class BoardNavigation
{
    /**
     * Modules dotés d'un espace multi-tableaux : contexte => board principal.
     *
     * @return array<string, array{resource: class-string, model: class-string, label: string, icon: string}>
     */
    public static function contextes(): array
    {
        return [
            'candidate' => ['resource' => CandidateResource::class, 'model' => Candidate::class, 'label' => 'Base Candidats', 'icon' => 'heroicon-o-user-group'],
            'company' => ['resource' => CompanyResource::class, 'model' => Company::class, 'label' => 'Base Entreprises', 'icon' => 'heroicon-o-building-office-2'],
            'need' => ['resource' => NeedResource::class, 'model' => Need::class, 'label' => 'Base Offres', 'icon' => 'heroicon-o-briefcase'],
        ];
    }

    /** Libellés des contextes (pour un sélecteur de rattachement, ex. à la création). */
    public static function optionsContexte(): array
    {
        return collect(self::contextes())->map(fn (array $c): string => $c['label'])->all();
    }

    /** La barre s'affiche-t-elle sur la page courante ? (uniquement si un contexte est identifié) */
    public static function doitAfficher(): bool
    {
        return self::contexteCourant() !== null;
    }

    /**
     * Contexte de module de la page courante : déduit de la ressource (Candidats /
     * Entreprises / Offres) ou, sur une page de tableau personnalisé, du contexte
     * du tableau consulté (ou du paramètre ?context= sur la page de création).
     */
    public static function contexteCourant(): ?string
    {
        $routeName = request()->route()?->getName();

        if ($routeName === null) {
            return null;
        }

        foreach (self::contextes() as $ctx => $cfg) {
            if (self::correspond($cfg['resource'], $routeName)) {
                return $ctx;
            }
        }

        if (self::correspond(CustomTableResource::class, $routeName)) {
            $record = request()->route('record');
            $id = $record instanceof Model ? $record->getKey() : $record;

            if ($id !== null) {
                $contexte = CustomTable::query()->whereKey($id)->value('context');

                return $contexte !== null && isset(self::contextes()[$contexte]) ? $contexte : null;
            }

            // Page de création : contexte transmis en query (?context=candidate).
            $contexte = request('context');

            return is_string($contexte) && isset(self::contextes()[$contexte]) ? $contexte : null;
        }

        return null;
    }

    /**
     * Les boards à afficher pour le contexte courant : le board principal du module
     * puis les tableaux personnalisés ACTIFS rattachés à ce module. Onglet actif
     * déduit de la route/record.
     *
     * @return list<array{key: string, label: string, icon: string, url: string, actif: bool}>
     */
    public static function boards(): array
    {
        $ctx = self::contexteCourant();

        if ($ctx === null) {
            return [];
        }

        $cfg = self::contextes()[$ctx];
        $routeName = request()->route()?->getName();
        $boards = [];

        // Board principal du module (ex. Base Candidats).
        if (Auth::user()?->can('viewAny', $cfg['model']) ?? false) {
            $boards[] = [
                'key' => 'base',
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'url' => $cfg['resource']::getUrl('index'),
                'actif' => self::correspond($cfg['resource'], $routeName),
            ];
        }

        // Tableaux personnalisés de CE module.
        if (Auth::user()?->can('viewAny', CustomTable::class) ?? false) {
            $recordActif = self::recordCustomTableActif();

            foreach (CustomTable::query()->actif()->where('context', $ctx)->orderBy('sort')->orderBy('name')->get() as $table) {
                $boards[] = [
                    'key' => 'table-'.$table->getKey(),
                    'label' => $table->name,
                    'icon' => $table->icon ?: 'heroicon-o-table-cells',
                    // Ouvre le board (les lignes), pas le formulaire de configuration.
                    'url' => CustomTableResource::getUrl('board', ['record' => $table]),
                    'actif' => $recordActif !== null && (string) $recordActif === (string) $table->getKey(),
                ];
            }
        }

        return $boards;
    }

    /** L'utilisateur peut-il créer un nouveau tableau dans ce module ? */
    public static function peutCreer(): bool
    {
        return Auth::user()?->can('create', CustomTable::class) ?? false;
    }

    /** URL de création d'un tableau, en transmettant le contexte du module courant. */
    public static function urlCreation(): string
    {
        $ctx = self::contexteCourant();
        $url = CustomTableResource::getUrl('create');

        if ($ctx === null) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'context='.urlencode($ctx);
    }

    /**
     * Variante testable : la route relève-t-elle d'un module à boards (ou d'une
     * page de tableau personnalisé) ? Le contexte précis est résolu par
     * {@see contexteCourant()} (qui, lui, peut consulter la base).
     */
    public static function doitAfficherPour(?string $routeName): bool
    {
        foreach (self::contextes() as $cfg) {
            if (self::correspond($cfg['resource'], $routeName)) {
                return true;
            }
        }

        return self::correspond(CustomTableResource::class, $routeName);
    }

    /** Identifiant du tableau personnalisé consulté (paramètre « record »), ou null. */
    private static function recordCustomTableActif(): ?string
    {
        if (! self::correspond(CustomTableResource::class, request()->route()?->getName())) {
            return null;
        }

        $record = request()->route('record');

        if ($record === null) {
            return null;
        }

        return $record instanceof Model ? (string) $record->getKey() : (string) $record;
    }

    /**
     * La route courante relève-t-elle d'une ressource ? Détection par PRÉFIXE du
     * nom de route Filament (couvre index/create/edit/view + relations).
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
