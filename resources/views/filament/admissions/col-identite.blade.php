@php $r = $getRecord(); $c = $r->candidate; @endphp
<div class="cfa-cand-identite">
    <span class="cfa-cand-avatar">{{ $c?->initiales ?? '?' }}</span>
    <span class="cfa-cand-identite-txt">
        <span class="cfa-cand-name">{{ $c?->nom_complet ?? 'Candidat supprimé' }}</span>
        @if ($r->contract?->company)
            <span class="cfa-cand-formation">{{ $r->contract->company->raison_sociale }}</span>
        @endif
    </span>
</div>
