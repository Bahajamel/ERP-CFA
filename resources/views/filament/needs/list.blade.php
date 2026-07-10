<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    {{-- Filtres rapides « orientés action » (cliquables) --}}
    <div class="cfa-cand-quick">
        <button type="button" wire:click="setQuickScope('a_pourvoir')"
            class="cfa-quick {{ $quickScope === 'a_pourvoir' ? 'actif' : '' }}" style="--q:#f59e0b"
            aria-pressed="{{ $quickScope === 'a_pourvoir' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-briefcase', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_pourvoir'] }} offres à pourvoir</b>
                <small>Postes ouverts en recrutement</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('sans_candidat')"
            class="cfa-quick {{ $quickScope === 'sans_candidat' ? 'actif' : '' }}" style="--q:#f43f5e"
            aria-pressed="{{ $quickScope === 'sans_candidat' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-exclamation-triangle', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['sans_candidat'] }} offres sans candidat</b>
                <small>À proposer au matching</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('en_matching')"
            class="cfa-quick {{ $quickScope === 'en_matching' ? 'actif' : '' }}" style="--q:#3b82f6"
            aria-pressed="{{ $quickScope === 'en_matching' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-arrows-right-left', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['en_matching'] }} offres en cours de matching</b>
                <small>Candidats en sélection</small>
            </span>
        </button>
    </div>

    {{-- Tableau des offres : clic sur une ligne = page de modification de l'offre. --}}
    <div class="cfa-cand-main">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
