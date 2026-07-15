<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Matching;
use App\Models\Need;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Vue 360° entreprise (P0-03-4) — fiche « premium » : mise en page custom
 * (en-tête, KPI, 2 colonnes) reprenant la même charte que la fiche candidat.
 * Identité, suivi commercial, besoins, candidats proposés et contrats en un
 * écran ; contacts & notes conservés en RelationManagers au bas de la page.
 */
class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    /** Fiche entreprise « premium » : mise en page custom. */
    protected string $view = 'filament.resources.companies.pages.view-company';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * Données réelles de la fiche (aucune donnée fictive) : KPI, suivi
     * commercial, besoins, candidats proposés, contrats, relance et notes —
     * le tout dérivé des relations existantes du modèle Company.
     */
    protected function getViewData(): array
    {
        $c = $this->getRecord();

        $besoins = $c->needs()->with('formation')->latest()->get()->map(fn (Need $n): array => [
            'poste' => $n->intitule_poste,
            'formation' => $n->formation?->libelle ?? '—',
            'statut' => $n->statut,
            'restants' => $n->postesRestants(),
            'total' => (int) $n->nb_postes,
        ]);

        $candidats = $c->matchings()->with(['candidate', 'need'])->latest('matchings.created_at')->get()
            ->map(fn (Matching $m): array => [
                'candidat' => $m->candidate?->nom_complet ?? '—',
                'besoin' => $m->need?->intitule_poste ?? '—',
                'statut' => $m->statut,
                'date' => $m->created_at,
            ]);

        $contrats = $c->contracts()->with(['candidate', 'opcoFile'])->latest()->get()
            ->map(fn (Contract $ct): array => [
                'apprenti' => $ct->candidate?->nom_complet ?? '—',
                'statut' => $ct->statut_contrat,
                'periode' => $ct->date_debut
                    ? $ct->date_debut->format('d/m/Y').' → '.($ct->date_fin?->format('d/m/Y') ?? '…')
                    : '—',
                'opco' => $ct->opcoFile?->statut,
            ]);

        return [
            'kpis' => [
                'besoinsOuverts' => $c->needs()->ouverts()->count(),
                'candidats' => $c->matchings()->count(),
                'contrats' => $c->contracts()->count(),
                'satisfaction' => $c->derniereSatisfaction(),
            ],
            'besoins' => $besoins,
            'candidats' => $candidats,
            'contrats' => $contrats,
            'formationsRecherchees' => $c->formationsRecherchees(),
            'contactPrincipal' => $c->contactPrincipal()->first() ?? $c->contacts()->first(),
            'relance' => $c->prochaineRelance(),
            'incidents' => $c->incidents()->count(),
            'notes' => $c->notes()->with('author')->latest()->get(),
        ];
    }
}
