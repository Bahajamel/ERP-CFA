<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    {{-- Filtres rapides = dossiers qui demandent une intervention (cliquables) --}}
    <div class="cfa-cand-quick">
        <button type="button" wire:click="setQuickScope('a_planifier')"
            class="cfa-quick {{ $quickScope === 'a_planifier' ? 'actif' : '' }}" style="--q:#3b82f6"
            aria-pressed="{{ $quickScope === 'a_planifier' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-calendar-days', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_planifier'] }} entretiens à planifier</b>
                <small>Premier échange à organiser</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('a_decider')"
            class="cfa-quick {{ $quickScope === 'a_decider' ? 'actif' : '' }}" style="--q:#f59e0b"
            aria-pressed="{{ $quickScope === 'a_decider' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-clipboard-document-check', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_decider'] }} décisions en attente</b>
                <small>Entretien réalisé — à accepter ou refuser</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('a_orienter')"
            class="cfa-quick {{ $quickScope === 'a_orienter' ? 'actif' : '' }}" style="--q:#10b981"
            aria-pressed="{{ $quickScope === 'a_orienter' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-paper-airplane', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_orienter'] }} acceptés à orienter</b>
                <small>À envoyer vers une entreprise</small>
            </span>
        </button>
    </div>

    {{-- Tableau opérationnel + panneau Focus (affiché seulement à la sélection) --}}
    @php $focus = $this->getFocusCandidate(); @endphp
    <div class="cfa-cand-layout {{ $focus ? 'has-focus' : '' }}">
        <div class="cfa-cand-main">
            {{ $this->table }}
        </div>
        @if ($focus)
            <aside class="cfa-cand-focus-col">
                @include('filament.candidates.focus-panel', ['c' => $focus])
            </aside>
        @endif
    </div>
</x-filament-panels::page>
