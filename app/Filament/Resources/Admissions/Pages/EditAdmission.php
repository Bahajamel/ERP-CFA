<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdmission extends EditRecord
{
    protected static string $resource = AdmissionResource::class;

    protected function getHeaderActions(): array
    {
        // « Valider le dossier » est volontairement placé en bas du dossier
        // (après les infos candidat et le CV), pas ici. Voir AdmissionForm.
        // L'action générique « Faire évoluer » a été retirée de la pré-admission :
        // le seul changement d'état exposé est la validation (gate CV).
        return [
            DeleteAction::make(),
        ];
    }
}
