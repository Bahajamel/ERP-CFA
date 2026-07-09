<x-filament-panels::page>
    @php($record = $this->getRecord())

    @include('filament.formation-catalogue', [
        'formation' => $record,
        'matieres' => $record->programme(),
        'nbClasses' => \App\Models\Promotion::where('formation_id', $record->id)->count(),
        'nbApprenants' => \App\Models\Candidate::where('formation_visee_id', $record->id)->count(),
    ])
</x-filament-panels::page>
