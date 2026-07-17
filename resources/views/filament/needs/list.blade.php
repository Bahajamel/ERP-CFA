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

        {{-- Les trois filtres ci-dessus ne montrent que des offres ouvertes :
             celui-ci est la seule porte vers l'historique (pourvues + annulées). --}}
        <button type="button" wire:click="setQuickScope('cloturees')"
            class="cfa-quick {{ $quickScope === 'cloturees' ? 'actif' : '' }}" style="--q:#64748b"
            aria-pressed="{{ $quickScope === 'cloturees' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-archive-box', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['cloturees'] }} offres clôturées</b>
                <small>Pourvues ou annulées</small>
            </span>
        </button>
    </div>

    {{-- Tableau des offres + panneau Focus (affiché seulement à la sélection) --}}
    @php $focus = $this->getFocusNeed(); @endphp
    <div class="cfa-cand-layout {{ $focus ? 'has-focus' : '' }}">
        <div class="cfa-cand-main">
            {{ $this->table }}
        </div>
        @if ($focus)
            <aside class="cfa-cand-focus-col">
                @include('filament.needs.focus-panel', ['o' => $focus])
            </aside>
        @endif
    </div>
</x-filament-panels::page>
