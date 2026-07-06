<?php

namespace App\Filament\Resources\Needs\Pages;

use App\Filament\Exports\NeedExporter;
use App\Filament\Resources\Needs\NeedResource;
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
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListNeeds extends ListRecords
{
    protected static string $resource = NeedResource::class;

    public function getSubheading(): ?string
    {
        return 'Un besoin = un poste à pourvoir chez une entreprise (métier, formation visée, rythme). '
            .'C\'est ce que le matching cherche à combler avec vos candidats. Suivez chaque besoin de '
            .'sa création jusqu\'au candidat retenu via la « Vue Pipeline ».';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pipeline')
                ->label('Vue Pipeline')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(NeedResource::getUrl('kanban')),
            CreateAction::make(),
            $this->prospecterAction(),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(NeedExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
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
