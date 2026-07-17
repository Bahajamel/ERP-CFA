<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Exports\CandidateExporter;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Tables\CandidatesTable;
use App\Models\Candidate;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Liste des candidats = workspace opérationnel.
 *
 * Au tableau Filament s'ajoutent trois filtres rapides « orientés action »
 * (dossiers à compléter, sans relance, entretiens à planifier) et un panneau
 * latéral « Focus du jour » qui se met à jour au clic sur une ligne — sans
 * navigation. La logique métier, les permissions et les actions du tableau
 * restent inchangées ; seule la présentation est enrichie.
 */
class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    protected string $view = 'filament.candidates.list';

    /** Candidat affiché dans le panneau « Focus du jour » (clic sur une ligne). */
    public ?int $focusId = null;

    /** Filtre rapide actif (null = aucun). */
    public ?string $quickScope = null;

    public function getSubheading(): ?string
    {
        return 'Gérez le cycle de vie des candidats : suivez chaque étape, relancez au bon moment et optimisez vos admissions.';
    }

    /** Active/désactive un filtre rapide (bascule si déjà actif). */
    public function setQuickScope(?string $scope): void
    {
        $this->quickScope = $this->quickScope === $scope ? null : $scope;
        $this->resetTable();
    }

    /**
     * Candidat courant du panneau Focus. Retourne null tant qu'aucune ligne
     * n'a été cliquée : le panneau n'apparaît qu'à la sélection.
     */
    public function getFocusCandidate(): ?Candidate
    {
        if ($this->focusId === null) {
            return null;
        }

        return Candidate::query()
            ->with(['formationVisee', 'commercial', 'interactions'])
            ->find($this->focusId);
    }

    /** Ferme le panneau Focus (croix). */
    public function unfocus(): void
    {
        $this->focusId = null;
    }

    /**
     * Compteurs des trois filtres rapides « dossiers qui demandent une
     * intervention » (sur la base non archivée).
     *
     * @return array{a_planifier:int,a_decider:int,a_orienter:int}
     */
    public function getQuickCounts(): array
    {
        return [
            'a_planifier' => Candidate::query()->tap(fn ($q) => CandidatesTable::appliquerScopeRapide($q, 'a_planifier'))->count(),
            'a_decider' => Candidate::query()->tap(fn ($q) => CandidatesTable::appliquerScopeRapide($q, 'a_decider'))->count(),
            'a_orienter' => Candidate::query()->tap(fn ($q) => CandidatesTable::appliquerScopeRapide($q, 'a_orienter'))->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pipeline')
                ->label('Vue Pipeline')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(CandidateResource::getUrl('kanban')),
            CreateAction::make()
                ->label('Créer un candidat'),
            // Bouton « Colonnes personnalisées » : modal pour ajouter ses propres
            // colonnes au tableau/fiches candidats (réservé Administrateur/Direction).
            CustomFields::gererAction('candidate', 'Candidats'),
            Action::make('lienCandidature')
                ->label('Lien de candidature')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Lien du formulaire de candidature')
                ->modalDescription('Envoyez ce lien à un candidat : il dépose son dossier et ses pièces sans accès à l\'ERP.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->schema([
                    Placeholder::make('outil')
                        ->hiddenLabel()
                        ->content(fn () => view('filament.candidature-lien', ['lien' => route('candidature.create')])),
                ]),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(CandidateExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }
}
