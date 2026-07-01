<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmission extends CreateRecord
{
    protected static string $resource = AdmissionResource::class;

    /** À l'ouverture du dossier, on génère les pièces obligatoires standard. */
    protected function afterCreate(): void
    {
        $this->record->genererChecklistObligatoire();
    }
}
