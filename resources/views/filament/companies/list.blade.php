<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    {{-- Filtres rapides « orientés action » (cliquables) --}}
    <div class="cfa-cand-quick">
        <button type="button" wire:click="setQuickScope('a_relancer')"
            class="cfa-quick {{ $quickScope === 'a_relancer' ? 'actif' : '' }}" style="--q:#f59e0b"
            aria-pressed="{{ $quickScope === 'a_relancer' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-building-office-2', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_relancer'] }} entreprises à relancer</b>
                <small>Sans activité récente</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('besoins_ouverts')"
            class="cfa-quick {{ $quickScope === 'besoins_ouverts' ? 'actif' : '' }}" style="--q:#3b82f6"
            aria-pressed="{{ $quickScope === 'besoins_ouverts' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-briefcase', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['besoins_ouverts'] }} entreprises avec besoins</b>
                <small>Postes à pourvoir</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('sans_besoin')"
            class="cfa-quick {{ $quickScope === 'sans_besoin' ? 'actif' : '' }}" style="--q:#8b5cf6"
            aria-pressed="{{ $quickScope === 'sans_besoin' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-inbox', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['sans_besoin'] }} partenaires sans besoin</b>
                <small>À solliciter pour de nouveaux postes</small>
            </span>
        </button>
    </div>

    {{-- Tableau opérationnel + panneau Focus (affiché seulement à la sélection) --}}
    @php $focus = $this->getFocusCompany(); @endphp
    <div class="cfa-cand-layout {{ $focus ? 'has-focus' : '' }}">
        <div class="cfa-cand-main">
            {{ $this->table }}
        </div>
        @if ($focus)
            <aside class="cfa-cand-focus-col">
                @include('filament.companies.focus-panel', ['e' => $focus])
            </aside>
        @endif
    </div>
</x-filament-panels::page>
