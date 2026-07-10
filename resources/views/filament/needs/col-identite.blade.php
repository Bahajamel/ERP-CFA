@php $r = $getRecord(); @endphp
<div class="cfa-cand-identite">
    <span class="cfa-cand-avatar cfa-avatar-alt">{{ $r->company?->initiales ?? '?' }}</span>
    <span class="cfa-cand-identite-txt">
        <span class="cfa-cand-name">{{ $r->intitule_poste }}</span>
        @if ($r->company)
            <span class="cfa-cand-formation">{{ $r->company->raison_sociale }}</span>
        @endif
    </span>
</div>
