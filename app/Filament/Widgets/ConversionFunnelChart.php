<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\OpcoFile;
use Filament\Widgets\ChartWidget;

/**
 * Entonnoir de conversion du CFA : de la candidature au financement.
 * Donne à la direction la santé du pipeline en un coup d'œil
 * (où « fuit » le tunnel — quelle étape perd le plus de dossiers).
 * Réservée à la Direction et à l'Administrateur.
 */
class ConversionFunnelChart extends ChartWidget
{
    protected ?string $heading = 'Entonnoir de conversion';

    protected ?string $description = 'De la candidature au financement OPCO';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $candidats = Candidate::whereNot('statut', CandidateStatut::Rupture->value)->count();
        $admissionsValidees = Admission::where('statut', AdmissionStatut::Valide->value)->count();
        $contratsSignes = Contract::whereIn('statut_contrat', [
            ContractStatut::Signe->value,
            ContractStatut::TransmisOpco->value,
            ContractStatut::Actif->value,
        ])->count();
        $opcoAcceptes = OpcoFile::where('statut', OpcoStatut::Accepte->value)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Dossiers',
                    'data' => [$candidats, $admissionsValidees, $contratsSignes, $opcoAcceptes],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.7)',   // bleu — candidats
                        'rgba(234, 179, 8, 0.7)',    // ambre — admissions
                        'rgba(16, 185, 129, 0.7)',   // émeraude — contrats
                        'rgba(139, 92, 246, 0.7)',   // violet — OPCO
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(234, 179, 8)',
                        'rgb(16, 185, 129)',
                        'rgb(139, 92, 246)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => [
                'Candidats actifs',
                'Admissions validées',
                'Contrats signés',
                'OPCO acceptés',
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
