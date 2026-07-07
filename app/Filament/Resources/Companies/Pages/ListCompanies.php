<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Exports\CompanyExporter;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

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
                        ->content(fn () => view('filament.candidature-lien', ['lien' => route('entreprise.create')])),
                ]),
            ExportAction::make()
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->exporter(CompanyExporter::class)
                ->visible(fn (): bool => Auth::user()?->can('access_reports') ?? false),
        ];
    }
}
