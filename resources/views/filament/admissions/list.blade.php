<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    {{-- Filtres rapides = dossiers qui demandent une intervention (cliquables) --}}
    <div class="cfa-cand-quick">
        <button type="button" wire:click="setQuickScope('a_verifier')"
            class="cfa-quick {{ $quickScope === 'a_verifier' ? 'actif' : '' }}" style="--q:#3b82f6"
            aria-pressed="{{ $quickScope === 'a_verifier' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-clipboard-document-check', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['a_verifier'] }} dossiers à vérifier</b>
                <small>En attente de validation</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('pretes')"
            class="cfa-quick {{ $quickScope === 'pretes' ? 'actif' : '' }}" style="--q:#10b981"
            aria-pressed="{{ $quickScope === 'pretes' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-check-badge', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['pretes'] }} prêtes à valider</b>
                <small>Pièces complètes — plus qu'à valider</small>
            </span>
        </button>

        <button type="button" wire:click="setQuickScope('pieces_manquantes')"
            class="cfa-quick {{ $quickScope === 'pieces_manquantes' ? 'actif' : '' }}" style="--q:#f43f5e"
            aria-pressed="{{ $quickScope === 'pieces_manquantes' ? 'true' : 'false' }}">
            <span class="cfa-quick-ico">@svg('heroicon-o-exclamation-triangle', 'w-5 h-5')</span>
            <span class="cfa-quick-txt">
                <b>{{ $qc['pieces_manquantes'] }} pièces manquantes</b>
                <small>À réclamer avant validation</small>
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
