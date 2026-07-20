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
use Filament\Actions\ActionGroup;
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
        // Sous-titre retiré (gain de place) — le titre « Base Candidats » suffit.
        return null;
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
            // Boutons directs : action principale + bascule de vue. Le reste est
            // regroupé dans un menu « Actions » (⋯) pour ne pas saturer l'écran.
            CreateAction::make()
                ->label('Créer un candidat'),
            Action::make('pipeline')
                ->label('Vue Pipeline')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(CandidateResource::getUrl('kanban')),
            ActionGroup::make([
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
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray'),
        ];
    }

    /* ----------------------------------------------------------------
     |  Actions rendues dans la barre custom (au-dessus du filtre) et
     |  au pied du tableau (« Ajouter un élément »).
     * ---------------------------------------------------------------- */

    /** Bouton « Ajouter une colonne » (barre, au-dessus du filtre). */
    public function ajouterColonneAction(): Action
    {
        // Nom « ajouterColonne » = méthode « ajouterColonneAction » → résolution OK.
        return CustomFields::gererAction('candidate', 'Candidats', 'ajouterColonne')
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
            ->visible(fn (): bool => Auth::user()?->can('create', CustomTable::class) ?? false)
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
                // Rattaché au module « Candidats » : ce tableau apparaîtra dans le
                // sélecteur de tables de la Base Candidats (plusieurs boards possibles).
                $tableau = CustomTable::create(['name' => $data['name'], 'context' => 'candidate']);
                CustomFields::synchroniserTableau($tableau->id, $data['colonnes'] ?? []);

                // Ouvre directement le board (les lignes) du nouveau tableau.
                return redirect(CustomTableResource::getUrl('board', ['record' => $tableau]));
            });
    }

    /** Bouton « Ajouter un candidat » (pied du tableau) : nouvelle ligne = nouveau candidat. */
    public function ajouterElementAction(): Action
    {
        return Action::make('ajouterElement')
            ->label('Ajouter un candidat')
            ->icon('heroicon-o-plus')
            ->link()
            ->color('primary')
            ->url(CandidateResource::getUrl('create'));
    }

    /** Bouton « Renommer les colonnes » (barre) : surcharge des libellés natifs par CFA. */
    public function renommerColonnesAction(): Action
    {
        // Nom « renommerColonnes » = méthode « renommerColonnesAction » → résolution OK.
        return CustomFields::personnaliserAction('candidate', 'Candidats', CandidatesTable::COLONNES_PERSONNALISABLES, 'renommerColonnes');
    }

    /** Bouton « Supprimer une colonne » (barre) : retire une colonne personnalisée du CFA. */
    public function supprimerColonneAction(): Action
    {
        // Nom « supprimerColonne » = méthode « supprimerColonneAction » → résolution OK.
        return CustomFields::supprimerColonneAction('candidate', 'Candidats', 'supprimerColonne');
    }

    /**
     * Mémorise la largeur d'une colonne (px) — appelé par le glisser-déposer souris
     * de l'en-tête ; $px null = réinitialisation (double-clic). N'agit que sur une
     * colonne connue et pour un utilisateur autorisé (contrôle serveur).
     */
    public function setLargeurColonne(string $key, ?int $px): void
    {
        if (! array_key_exists($key, CandidatesTable::COLONNES_PERSONNALISABLES)) {
            return;
        }

        CustomFields::definirLargeur('candidate', $key, $px);
        $this->resetTable();
    }

    /**
     * Mémorise l'ordre des colonnes (façon Monday) — appelé par le glisser-déposer
     * des en-têtes. Ne conserve que les clés de colonnes connues (natives + custom
     * du CFA) : toute clé étrangère est ignorée (contrôle serveur).
     *
     * @param  array<int, string>  $cles
     */
    public function setOrdreColonnes(array $cles): void
    {
        $autorisees = array_merge(
            array_keys(CandidatesTable::COLONNES_PERSONNALISABLES),
            CustomFields::definitions('candidate')->map(fn ($d): string => 'custom_fields.'.$d->key)->all(),
        );

        $cles = array_values(array_filter($cles, fn ($cle): bool => in_array($cle, $autorisees, true)));

        if ($cles === []) {
            return;
        }

        CustomFields::definirOrdre('candidate', $cles);
        $this->resetTable();
    }
}
