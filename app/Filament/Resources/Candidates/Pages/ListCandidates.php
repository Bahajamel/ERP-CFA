<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Exports\CandidateExporter;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Tables\CandidatesTable;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Models\Candidate;
use App\Models\CustomTable;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
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

    public function getTitle(): string
    {
        return 'Base Candidats';
    }

    public function getSubheading(): ?string
    {
        return 'Suivi des candidats et de leur avancement';
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

    /* ----------------------------------------------------------------
     |  Actions rendues dans la barre custom (au-dessus du filtre) et
     |  au pied du tableau (« Ajouter un élément »).
     * ---------------------------------------------------------------- */

    /** Bouton « Ajouter une colonne » (barre, au-dessus du filtre). */
    public function ajouterColonneAction(): Action
    {
        return CustomFields::gererAction('candidate', 'Candidats')
            ->label('Ajouter une colonne')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('gray');
    }

    /** Bouton « Nouveau tableau » (barre) : crée un tableau personnalisé puis l'ouvre. */
    public function nouveauTableauAction(): Action
    {
        return Action::make('nouveauTableau')
            ->label('Nouveau tableau')
            ->icon('heroicon-o-table-cells')
            ->button()
            ->color('gray')
            ->visible(fn (): bool => CustomFields::peutGerer())
            ->modalHeading('Créer un tableau personnalisé')
            ->modalDescription('Donnez-lui un nom et définissez ses colonnes. Vous saisirez les lignes juste après.')
            ->modalSubmitActionLabel('Créer le tableau')
            ->modalWidth('3xl')
            ->schema([
                TextInput::make('name')
                    ->label('Nom du tableau')
                    ->placeholder('ex : Suivi partenariats, Événements…')
                    ->required()
                    ->maxLength(255),
                CustomFields::repeaterColonnes(),
            ])
            ->action(function (array $data) {
                $tableau = CustomTable::create(['name' => $data['name']]);
                CustomFields::synchroniserTableau($tableau->id, $data['colonnes'] ?? []);

                return redirect(CustomTableResource::getUrl('edit', ['record' => $tableau]));
            });
    }

    /** Bouton « Ajouter un élément » (pied du tableau) : nouvelle ligne = nouveau candidat. */
    public function ajouterElementAction(): Action
    {
        return Action::make('ajouterElement')
            ->label('Ajouter un élément')
            ->icon('heroicon-o-plus')
            ->link()
            ->color('primary')
            ->url(CandidateResource::getUrl('create'));
    }
}
