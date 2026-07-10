<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    {{-- Filtres rapides « orientés action » (cliquables) --}}
    <div class="cfa-cand-quick">
        <button type="button" wire:click="setQuickScope('a_completer')"
            class="cfa-quick {{ $quickScope === 'a_completer' ? 'actif' : '' }}" style="--q:#8b5cf6"
            aria-pressed="{{ $quickScope === 'a_completer' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-document-text', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_completer'] }} dossiers à compléter</b>
                <small>Action requise</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('sans_relance')"
            class="cfa-quick {{ $quickScope === 'sans_relance' ? 'actif' : '' }}" style="--q:#f59e0b"
            aria-pressed="{{ $quickScope === 'sans_relance' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-exclamation-triangle', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['sans_relance'] }} sans relance depuis 7 jours</b>
                <small>Risque de perte</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('a_planifier')"
            class="cfa-quick {{ $quickScope === 'a_planifier' ? 'actif' : '' }}" style="--q:#3b82f6"
            aria-pressed="{{ $quickScope === 'a_planifier' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-calendar-days', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_planifier'] }} entretiens à planifier</b>
                <small>À organiser cette semaine</small>
            </span>
        </button>
    </div>

    {{-- Tableau opérationnel + panneau Focus --}}
    <div class="cfa-cand-layout">
        <div class="cfa-cand-main">
            {{ $this->table }}
        </div>
        <aside class="cfa-cand-focus-col">
            @include('filament.candidates.focus-panel', ['c' => $this->getFocusCandidate()])
        </aside>
    </div>
</x-filament-panels::page>
