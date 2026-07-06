<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Exports\CompanyExporter;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Formation;
use App\Prospecting\LaBonneAlternanceClient;
use App\Prospecting\ProspectionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    public function getSubheading(): ?string
    {
        return 'Les entreprises partenaires qui accueillent vos apprentis. Enregistrez l\'entreprise, '
            .'ses contacts et ses tuteurs (maîtres d\'apprentissage), puis exprimez ses besoins de '
            .'recrutement pour lancer le matching avec vos candidats.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->prospecterAction(),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(CompanyExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }

    /**
     * Prospection La Bonne Alternance : pour une formation, importe les entreprises
     * qui recrutent en alternance (autour du CFA) comme prospects + besoin à qualifier.
     */
    private function prospecterAction(): Action
    {
        return Action::make('prospecterLba')
            ->label('Prospecter (La Bonne Alternance)')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('primary')
            ->visible(fn (): bool => Auth::user()?->can('access_companies') ?? false)
            ->modalHeading('Prospecter des entreprises qui recrutent en alternance')
            ->modalDescription('Source : API publique La Bonne Alternance (entreprises identifiées comme susceptibles de recruter). Usage réservé au non-lucratif.')
            ->schema([
                Select::make('formation_id')
                    ->label('Formation ciblée')
                    ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                    ->searchable()
                    ->required()
                    ->helperText('La recherche cible le métier via le code RNCP de la formation.'),
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

                $center = config('services.labonnealternance.center');

                try {
                    $r = app(ProspectionService::class)->prospectForFormation(
                        $formation,
                        (float) $center['lat'],
                        (float) $center['lon'],
                        (int) ($data['radius'] ?? config('services.labonnealternance.default_radius')),
                    );

                    Notification::make()
                        ->success()
                        ->title('Prospection terminée')
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
}
