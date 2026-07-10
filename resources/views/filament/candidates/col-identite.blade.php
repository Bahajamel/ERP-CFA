@php $r = $getRecord(); @endphp
<div class="cfa-cand-identite">
    <span class="cfa-cand-avatar">{{ $r->initiales }}</span>
    <span class="cfa-cand-identite-txt">
        <span class="cfa-cand-name">{{ $r->nom_complet }}</span>
        @if ($r->formationVisee)
            <span class="cfa-cand-formation">{{ $r->formationVisee->libelle }}</span>
        @endif
    </span>
</div>
