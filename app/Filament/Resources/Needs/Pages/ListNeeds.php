<?php

namespace App\Filament\Resources\Needs\Pages;

use App\Filament\Exports\NeedExporter;
use App\Filament\Resources\Needs\NeedResource;
use App\Filament\Resources\Needs\Tables\NeedsTable;
use App\Models\Formation;
use App\Models\Need;
use App\Prospecting\AddressGeocoder;
use App\Prospecting\LaBonneAlternanceClient;
use App\Prospecting\ProspectionService;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Throwable;

class ListNeeds extends ListRecords
{
    protected static string $resource = NeedResource::class;

    protected string $view = 'filament.needs.list';

    /** Filtre rapide actif (null = aucun). */
    public ?string $quickScope = null;

    /** Offre affichée dans le panneau Focus (clic sur une ligne). */
    public ?int $focusId = null;

    /** Ferme le panneau Focus. */
    public function unfocus(): void
    {
        $this->focusId = null;
    }

    /** Offre courante du panneau Focus (null tant qu'aucune sélection). */
    public function getFocusNeed(): ?Need
    {
        if ($this->focusId === null) {
            return null;
        }

        return Need::query()
            ->with(['company', 'formation', 'matchings.candidate'])
            ->find($this->focusId);
    }

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

    /**
     * Compteurs des filtres rapides.
     *
     * @return array{a_pourvoir:int,sans_candidat:int,en_matching:int,cloturees:int}
     */
    public function getQuickCounts(): array
    {
        return [
            'a_pourvoir' => Need::query()->tap(fn ($q) => NeedsTable::appliquerScopeRapide($q, 'a_pourvoir'))->count(),
            'sans_candidat' => Need::query()->tap(fn ($q) => NeedsTable::appliquerScopeRapide($q, 'sans_candidat'))->count(),
            'en_matching' => Need::query()->tap(fn ($q) => NeedsTable::appliquerScopeRapide($q, 'en_matching'))->count(),
            'cloturees' => Need::query()->tap(fn ($q) => NeedsTable::appliquerScopeRapide($q, 'cloturees'))->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Boutons directs : action principale + bascule de vue. Le reste est
            // regroupé dans un menu « Actions » (⋯) pour ne pas saturer l'écran.
            CreateAction::make(),
            Action::make('pipeline')
                ->label('Vue Pipeline')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(NeedResource::getUrl('kanban')),
            ActionGroup::make([
                $this->prospecterAction(),
                ExportAction::make()
                    ->label('Exporter')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->exporter(NeedExporter::class)
                    ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray'),
        ];
    }

    /**
     * Prospection La Bonne Alternance : pour une formation, importe les entreprises
     * qui recrutent en alternance (autour du CFA) et crée un besoin « à qualifier »
     * pour chacune — ces besoins apparaissent directement dans la liste.
     */
    private function prospecterAction(): Action
    {
        return Action::make('prospecterLba')
            ->label('Prospecter (La Bonne Alternance)')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('primary')
            ->visible(fn (): bool => Auth::user()?->can('access_needs') ?? false)
            ->modalHeading('Prospecter des entreprises qui recrutent en alternance')
            ->modalDescription('Source : API publique La Bonne Alternance (entreprises identifiées comme susceptibles de recruter). Usage réservé au non-lucratif.')
            ->schema([
                Select::make('formation_id')
                    ->label('Formation ciblée')
                    ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                    ->searchable()
                    ->required()
                    ->helperText('La recherche cible le métier via le code RNCP de la formation. Un besoin est créé pour chaque entreprise trouvée.'),
                TextInput::make('lieu')
                    ->label('Lieu')
                    ->placeholder('Ville ou code postal (ex. Lyon, 69003)')
                    ->helperText('Laisser vide pour rechercher autour du CFA.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        // Géocode le lieu saisi et mémorise les coordonnées pour la
                        // carte et la recherche (évite un second appel à l'import).
                        $set('lieu_lat', null);
                        $set('lieu_lon', null);
                        $set('lieu_label', null);

                        if (blank($state)) {
                            return;
                        }

                        $geo = app(AddressGeocoder::class)->geocode(trim($state));

                        if ($geo !== null) {
                            $set('lieu_lat', $geo['lat']);
                            $set('lieu_lon', $geo['lon']);
                            $set('lieu_label', $geo['label']);
                        }
                    }),
                Hidden::make('lieu_lat'),
                Hidden::make('lieu_lon'),
                Hidden::make('lieu_label'),
                Placeholder::make('carte')
                    ->label('Carte')
                    ->content(fn (Get $get): HtmlString => self::carte($get('lieu_lat'), $get('lieu_lon'), $get('lieu_label'))),
                DatePicker::make('date_debut')
                    ->label('Date de début souhaitée')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->helperText('Reportée sur chaque besoin créé (date de démarrage).'),
                TextInput::make('radius')
                    ->label('Rayon de recherche (km)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(200)
                    ->default((int) config('services.labonnealternance.default_radius')),
            ])
            ->action(function (array $data): void {
                $client = app(LaBonneAlternanceClient::class);

                if (! $client->isConfigured()) {
                    Notification::make()
                        ->warning()
                        ->title('API non configurée')
                        ->body('Renseignez LBA_API_KEY dans le fichier .env pour activer la prospection.')
                        ->send();

                    return;
                }

                $formation = Formation::find($data['formation_id']);

                if ($formation === null || blank($formation->code_rncp)) {
                    Notification::make()
                        ->warning()
                        ->title('Formation sans code RNCP')
                        ->body('Renseignez le code RNCP de la formation pour cibler le métier.')
                        ->send();

                    return;
                }

                // Lieu saisi → coordonnées déjà géocodées (champ réactif) ou
                // géocodage de secours ; sinon on centre sur le CFA.
                $lieu = trim((string) ($data['lieu'] ?? ''));
                $center = config('services.labonnealternance.center');
                $latitude = (float) $center['lat'];
                $longitude = (float) $center['lon'];
                $lieuLabel = 'autour du CFA';

                if ($lieu !== '') {
                    $geo = (filled($data['lieu_lat'] ?? null) && filled($data['lieu_lon'] ?? null))
                        ? ['lat' => (float) $data['lieu_lat'], 'lon' => (float) $data['lieu_lon'], 'label' => $data['lieu_label'] ?? $lieu]
                        : app(AddressGeocoder::class)->geocode($lieu);

                    if ($geo === null) {
                        Notification::make()
                            ->warning()
                            ->title('Lieu introuvable')
                            ->body("Impossible de localiser « {$lieu} ». Vérifiez la ville ou le code postal.")
                            ->send();

                        return;
                    }

                    $latitude = $geo['lat'];
                    $longitude = $geo['lon'];
                    $lieuLabel = $geo['label'];
                }

                try {
                    $r = app(ProspectionService::class)->prospectForFormation(
                        $formation,
                        $latitude,
                        $longitude,
                        (int) ($data['radius'] ?? config('services.labonnealternance.default_radius')),
                        $data['date_debut'] ?? null,
                    );

                    Notification::make()
                        ->success()
                        ->title("Prospection terminée ({$lieuLabel})")
                        ->body("{$r['found']} entreprise(s) trouvée(s) · {$r['imported']} importée(s) · {$r['linked']} déjà connue(s) · {$r['skipped']} sans SIRET")
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Échec de la prospection')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /**
     * Aperçu OpenStreetMap centré sur le lieu géocodé (iframe, sans clé ni
     * dépendance). Message d'invite tant qu'aucun lieu n'est saisi.
     */
    private static function carte(mixed $lat, mixed $lon, mixed $label): HtmlString
    {
        if (blank($lat) || blank($lon)) {
            return new HtmlString(
                '<div style="height:120px;display:flex;align-items:center;justify-content:center;'
                .'border:1px dashed rgba(128,128,128,.4);border-radius:8px;color:rgba(128,128,128,.9);font-size:.85rem;">'
                .'Saisissez un lieu pour afficher la carte 🗺️</div>'
            );
        }

        $lat = (float) $lat;
        $lon = (float) $lon;
        $d = 0.04; // demi-fenêtre (~4 km) autour du point
        $bbox = ($lon - $d).','.($lat - $d).','.($lon + $d).','.($lat + $d);
        $src = 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik&marker='.$lat.','.$lon;

        return new HtmlString(
            '<iframe src="'.e($src).'" style="width:100%;height:260px;border:0;border-radius:8px;" loading="lazy" title="Carte du lieu"></iframe>'
            .'<div style="margin-top:.35rem;font-size:.8rem;color:rgba(128,128,128,.95);">📍 '.e((string) $label).'</div>'
        );
    }

    /* ----------------------------------------------------------------
     |  Colonnes personnalisées du CFA (barre au-dessus du tableau).
     * ---------------------------------------------------------------- */

    /** Bouton « Ajouter une colonne » (colonnes personnalisées Offres). */
    public function ajouterColonneAction(): Action
    {
        return CustomFields::gererAction('need', 'Offres', 'ajouterColonne')
            ->label('Ajouter une colonne')
            ->icon('heroicon-o-plus')
            ->button()
            ->color('gray');
    }

    /** Bouton « Renommer les colonnes » (surcharge des libellés natifs par CFA). */
    public function renommerColonnesAction(): Action
    {
        return CustomFields::personnaliserAction('need', 'Offres', NeedsTable::COLONNES_PERSONNALISABLES, 'renommerColonnes');
    }

    /** Bouton « Supprimer une colonne » (retire une colonne personnalisée du CFA). */
    public function supprimerColonneAction(): Action
    {
        return CustomFields::supprimerColonneAction('need', 'Offres', 'supprimerColonne');
    }
}
