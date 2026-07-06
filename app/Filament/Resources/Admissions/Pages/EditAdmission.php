<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionActions;
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
        return [
            AdmissionActions::changerStatut(),
            DeleteAction::make(),
        ];
    }
}
