<?php

namespace App\Filament\Resources\ServiceFaits\Pages;

use App\Filament\Exports\ServiceFaitExporter;
use App\Filament\Resources\ServiceFaits\ServiceFaitResource;
use App\Models\Promotion;
use App\Scolarite\ServiceFaitValidator;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ListServiceFaits extends ListRecords
{
    protected static string $resource = ServiceFaitResource::class;

    public function getSubheading(): ?string
    {
        return 'La preuve d\'assiduité qui justifie le financement OPCO : validez chaque mois une fois '
            .'toutes ses séances renseignées. Le service fait est alors figé (taux d\'assiduité, '
            .'nombre de séances) — c\'est la pièce qui sécurise le versement.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validerMois')
                ->label('Valider un mois')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->modalHeading('Valider le service fait d\'un mois')
                ->modalDescription('Toutes les séances du mois doivent être validées. Le service fait sera figé.')
                ->schema([
                    Select::make('promotion_id')
                        ->label('Classe')
                        ->options(Promotion::query()->orderBy('libelle')->pluck('libelle', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('mois')
                        ->label('Mois')
                        ->options(collect(range(1, 12))->mapWithKeys(fn (int $m): array => [
                            $m => ucfirst(Carbon::create(null, $m, 1)->locale('fr')->monthName),
                        ])->all())
                        ->default((int) now()->format('n'))
                        ->required(),
                    Select::make('annee')
                        ->label('Année')
                        ->options(collect(range((int) now()->format('Y') - 1, (int) now()->format('Y') + 1))
                            ->mapWithKeys(fn (int $y): array => [$y => (string) $y])->all())
                        ->default((int) now()->format('Y'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $promotion = Promotion::find($data['promotion_id']);

                    try {
                        $sf = app(ServiceFaitValidator::class)
                            ->valider($promotion, (int) $data['annee'], (int) $data['mois'], Auth::id());

                        Notification::make()
                            ->success()
                            ->title('Service fait validé')
                            ->body("{$promotion->libelle} — {$sf->periodeLibelle()} : {$sf->nb_seances} séances, {$sf->taux_presence} % d'assiduité.")
                            ->send();
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Validation impossible')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(ServiceFaitExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }
}
