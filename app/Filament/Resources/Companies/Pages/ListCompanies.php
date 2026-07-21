<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Exports\CompanyExporter;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Tables\CompaniesTable;
use App\Filament\Resources\Needs\NeedResource;
use App\Models\Company;
use App\Support\CfaPublic;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Liste des entreprises = workspace opérationnel (même logique que Candidats).
 *
 * Trois filtres rapides orientés action (à relancer / avec besoins ouverts /
 * prospects) et un panneau latéral « Focus entreprise » qui se met à jour au
 * clic sur une ligne — contact principal, tuteur, besoins en cours, matching
 * suggéré (candidats scorés), conseil contextuel et interactions. La logique
 * métier, les permissions et les actions restent inchangées.
 */
class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected string $view = 'filament.companies.list';

    /** Entreprise affichée dans le panneau Focus (clic sur une ligne). */
    public ?int $focusId = null;

    /** Filtre rapide actif (null = aucun). */
    public ?string $quickScope = null;

    public function getSubheading(): ?string
    {
        // Sous-titre retiré (gain de place).
        return null;
    }

    /** Active/désactive un filtre rapide (bascule si déjà actif). */
    public function setQuickScope(?string $scope): void
    {
        $this->quickScope = $this->quickScope === $scope ? null : $scope;
        $this->resetTable();
    }

    /** Ferme le panneau Focus. */
    public function unfocus(): void
    {
        $this->focusId = null;
    }

    /** Entreprise courante du panneau Focus (null tant qu'aucune sélection). */
    public function getFocusCompany(): ?Company
    {
        if ($this->focusId === null) {
            return null;
        }

        return Company::query()
            ->with(['opco', 'contacts', 'needs', 'interactions'])
            ->find($this->focusId);
    }

    /**
     * Compteurs des trois filtres rapides.
     *
     * @return array{a_relancer:int,besoins_ouverts:int,sans_besoin:int}
     */
    public function getQuickCounts(): array
    {
        return [
            'a_relancer' => Company::query()->tap(fn ($q) => CompaniesTable::appliquerScopeRapide($q, 'a_relancer'))->count(),
            'besoins_ouverts' => Company::query()->tap(fn ($q) => CompaniesTable::appliquerScopeRapide($q, 'besoins_ouverts'))->count(),
            'sans_besoin' => Company::query()->tap(fn ($q) => CompaniesTable::appliquerScopeRapide($q, 'sans_besoin'))->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Boutons directs : action principale + bascule de vue. Le reste est
            // regroupé dans un menu « Actions » (⋯) pour ne pas saturer l'écran.
            CreateAction::make(),
            Action::make('vueBesoins')
                ->label('Vue besoins')
                ->icon(Heroicon::OutlinedBriefcase)
                ->color('gray')
                ->url(NeedResource::getUrl()),
            ActionGroup::make([
                Action::make('lienEntreprise')
                    ->label('Lien entreprise')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('Lien du formulaire entreprise partenaire')
                    ->modalDescription('Envoyez ce lien à une entreprise : elle s\'enregistre (infos auto-remplies via son SIRET) sans accès à l\'ERP.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->schema([
                        Placeholder::make('outil')
                            ->hiddenLabel()
                            // Lien du CFA courant : chaque CFA distribue le sien,
                            // les entreprises lui reviennent directement.
                            ->content(fn () => view('filament.candidature-lien', [
                                'lien' => CfaPublic::lien('entreprise.create', Filament::getTenant()),
                            ])),
                    ]),
                ExportAction::make()
                    ->label('Exporter')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->exporter(CompanyExporter::class)
                    ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray'),
        ];
    }

    /* ----------------------------------------------------------------
     |  Colonnes personnalisées du CFA (barre au-dessus du tableau).
     * ---------------------------------------------------------------- */

    /** Bouton « Ajouter une colonne » (colonnes personnalisées Entreprises). */
    public function ajouterColonneAction(): Action
    {
        return CustomFields::gererAction('company', 'Entreprises', 'ajouterColonne')
            ->label('Ajouter une colonne')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('gray');
    }

    /** Bouton « Renommer les colonnes » (surcharge des libellés natifs par CFA). */
    public function renommerColonnesAction(): Action
    {
        return CustomFields::personnaliserAction('company', 'Entreprises', CompaniesTable::COLONNES_PERSONNALISABLES, 'renommerColonnes');
    }

    /** Bouton « Supprimer une colonne » (retire une colonne personnalisée du CFA). */
    public function supprimerColonneAction(): Action
    {
        return CustomFields::supprimerColonneAction('company', 'Entreprises', 'supprimerColonne');
    }
}
