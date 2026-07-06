<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmission extends CreateRecord
{
    protected static string $resource = AdmissionResource::class;

    // Pré-admission : aucune checklist de documents n'est générée à l'ouverture —
    // le seul document requis est le CV, porté par le candidat.
}
