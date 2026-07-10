@php $r = $getRecord(); @endphp
<div class="cfa-cand-identite">
    <span class="cfa-cand-avatar cfa-avatar-alt">{{ $r->initiales }}</span>
    <span class="cfa-cand-identite-txt">
        <span class="cfa-cand-name">{{ $r->raison_sociale }}</span>
        @if ($r->nom_commercial)
            <span class="cfa-cand-formation">{{ $r->nom_commercial }}</span>
        @endif
    </span>
</div>
