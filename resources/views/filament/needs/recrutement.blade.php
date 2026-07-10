@php
    $r = $getRecord();
    $demandes = max(1, (int) $r->nb_postes);
    $restants = $r->postesRestants();
    $pourvus = max(0, $demandes - $restants);
    $pct = (int) round($pourvus / $demandes * 100);
    $complet = $restants === 0;
@endphp
<div class="cfa-need-bar" role="img"
     aria-label="{{ $pourvus }} poste(s) pourvu(s) sur {{ $demandes }}">
    <div class="cfa-need-bar-track">
        <span class="cfa-need-bar-fill {{ $complet ? 'complet' : '' }}" style="width: {{ $pct }}%"></span>
    </div>
    <span class="cfa-need-bar-lbl">{{ $pourvus }}/{{ $demandes }} pourvu{{ $demandes > 1 ? 's' : '' }}</span>
</div>
