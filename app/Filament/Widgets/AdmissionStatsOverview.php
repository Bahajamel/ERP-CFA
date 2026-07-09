<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\OpcoFile;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard admission / administratif (P0-12-3) : les dossiers à traiter côté
 * back-office — admissions à finaliser, dossiers incomplets, contrats et
 * dossiers OPCO en attente d'action. Cartes cliquables vers les listes.
 */
class AdmissionStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Dossiers à traiter';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** Statuts d'admission nécessitant une action back-office. */
    private const ADMISSIONS_A_TRAITER = [
        AdmissionStatut::AVerifier->value,
    ];

    /** Statuts de contrat avant signature (à finaliser par l'administratif). */
    private const CONTRATS_A_TRAITER = [
        ContractStatut::EnCours->value,
        ContractStatut::ManqueSignature->value,
        ContractStatut::ACorriger->value,
    ];

    /** Dossiers OPCO en attente d'action (préparation / dépôt / correction). */
    private const OPCO_A_TRAITER = [
        OpcoStatut::APreparer->value,
        OpcoStatut::PretDepot->value,
        OpcoStatut::EnCorrection->value,
        OpcoStatut::Corrige->value,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Admission', 'Administratif', 'Administrateur']) ?? false;
    }

    protected function getStats(): array
    {
        $admissionsATraiter = Admission::whereIn('statut', self::ADMISSIONS_A_TRAITER)->count();
        $candidatsEnAttente = Candidate::whereIn('statut', array_map(
            fn (CandidateStatut $s) => $s->value,
            CandidateStatut::statutsEntretien(),
        ))->count();
        $contratsATraiter = Contract::whereIn('statut_contrat', self::CONTRATS_A_TRAITER)->count();
        $opcoATraiter = OpcoFile::whereIn('statut', self::OPCO_A_TRAITER)->count();

        return [
            Stat::make('Admissions à traiter', $admissionsATraiter)
                ->description('Admissions officielles à vérifier')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($admissionsATraiter > 0 ? 'warning' : 'success')
                ->url(AdmissionResource::getUrl()),
            Stat::make('Candidats en entretien', $candidatsEnAttente)
                ->description('Décision CFA attendue')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($candidatsEnAttente > 0 ? 'warning' : 'success')
                ->url(CandidateResource::getUrl()),
            Stat::make('Contrats à traiter', $contratsATraiter)
                ->description('Avant signature')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($contratsATraiter > 0 ? 'warning' : 'gray')
                ->url(ContractResource::getUrl()),
            Stat::make('Dossiers OPCO à traiter', $opcoATraiter)
                ->description('À préparer, déposer ou corriger')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($opcoATraiter > 0 ? 'warning' : 'gray')
                ->url(OpcoFileResource::getUrl()),
        ];
    }
}
