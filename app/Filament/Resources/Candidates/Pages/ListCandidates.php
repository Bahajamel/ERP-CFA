<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Exports\CandidateExporter;
use App\Filament\Resources\Candidates\CandidateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    public function getSubheading(): ?string
    {
        return 'Le point de départ du cycle apprenant : chaque futur apprenti entre ici. '
            .'Créez une fiche, suivez son statut (prospect → dossier complet), puis ouvrez son '
            .'admission. La « Vue Pipeline » montre l\'avancement de tous les candidats en colonnes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pipeline')
                ->label('Vue Pipeline')
                ->icon(Heroicon::OutlinedViewColumns)
                ->color('gray')
                ->url(CandidateResource::getUrl('kanban')),
            CreateAction::make(),
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
