<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Resources\Evaluations\EvaluationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEvaluation extends CreateRecord
{
    protected static string $resource = EvaluationResource::class;

    /** Trace l'auteur de la note (formateur connecté). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] ??= Auth::id();

        return $data;
    }
}
