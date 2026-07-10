<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    {{-- Filtres rapides = cycle de validation officielle (cliquables) --}}
    <div class="cfa-cand-quick">
        <button type="button" wire:click="setQuickScope('a_valider')"
            class="cfa-quick {{ $quickScope === 'a_valider' ? 'actif' : '' }}" style="--q:#3b82f6"
            aria-pressed="{{ $quickScope === 'a_valider' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-clipboard-document-check', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_valider'] }} à valider</b>
                <small>Apprenants à inscrire officiellement</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('inscrits')"
            class="cfa-quick {{ $quickScope === 'inscrits' ? 'actif' : '' }}" style="--q:#10b981"
            aria-pressed="{{ $quickScope === 'inscrits' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-check-badge', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['inscrits'] }} inscrits</b>
                <small>Apprenants officiellement inscrits</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('en_rupture')"
            class="cfa-quick {{ $quickScope === 'en_rupture' ? 'actif' : '' }}" style="--q:#f43f5e"
            aria-pressed="{{ $quickScope === 'en_rupture' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-exclamation-triangle', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['en_rupture'] }} en rupture</b>
                <small>Contrats rompus</small>
            </span>
        </button>
    </div>

    {{-- Tableau + panneau Focus (affiché seulement à la sélection) --}}
    @php $focus = $this->getFocusAdmission(); @endphp
    <div class="cfa-cand-layout {{ $focus ? 'has-focus' : '' }}">
        <div class="cfa-cand-main">
            {{ $this->table }}
        </div>
        @if ($focus)
            <aside class="cfa-cand-focus-col">
                @include('filament.admissions.focus-panel', ['a' => $focus])
            </aside>
        @endif
    </div>
</x-filament-panels::page>
