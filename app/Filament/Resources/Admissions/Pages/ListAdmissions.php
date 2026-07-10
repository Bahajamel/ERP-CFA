<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Admissions\Tables\AdmissionsTable;
use App\Models\Admission;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdmissions extends ListRecords
{
    protected static string $resource = AdmissionResource::class;

    protected string $view = 'filament.admissions.list';

    /** Filtre rapide actif (null = aucun). */
    public ?string $quickScope = null;

    /** Admission affichée dans le panneau Focus (clic sur une ligne). */
    public ?int $focusId = null;

    public function getSubheading(): ?string
    {
        return 'La vérification du dossier avant contractualisation : contrôlez les pièces obligatoires, '
            .'puis validez ou refusez l\'admission. Un dossier incomplet bloque le passage à l\'étape '
            .'suivante — la protection du financement commence ici.';
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

    /** Admission courante du panneau Focus (null tant qu'aucune sélection). */
    public function getFocusAdmission(): ?Admission
    {
        if ($this->focusId === null) {
            return null;
        }

        return Admission::query()
            ->with(['candidate.documents', 'contract.company', 'contract.formation', 'contract.opcoFile', 'contract.rupture'])
            ->find($this->focusId);
    }

    /**
     * Compteurs des trois filtres rapides (cycle de validation officielle).
     *
     * @return array{a_valider:int,inscrits:int,en_rupture:int}
     */
    public function getQuickCounts(): array
    {
        return [
            'a_valider' => Admission::query()->whereHas('candidate')->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'a_valider'))->count(),
            'inscrits' => Admission::query()->whereHas('candidate')->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'inscrits'))->count(),
            'en_rupture' => Admission::query()->whereHas('candidate')->tap(fn ($q) => AdmissionsTable::appliquerScopeRapide($q, 'en_rupture'))->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
