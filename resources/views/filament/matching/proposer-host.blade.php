{{-- Hôte de la modale « Proposer des candidats » : monte le composant Livewire dédié. --}}
@livewire(\App\Livewire\ProposerCandidatsModal::class, ['needId' => $need->id], key('proposer-'.$need->id))
