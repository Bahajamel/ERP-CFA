@php $etapes = $getRecord()->progressionEtapes(); @endphp
<div class="cfa-prog" role="img"
     aria-label="Progression : {{ collect($etapes)->firstWhere('etat', 'current')['label'] ?? 'terminée' }}">
    @foreach ($etapes as $e)
        <div class="cfa-prog-step cfa-prog-{{ $e['etat'] }}" title="{{ $e['label'] }}">
            <span class="cfa-prog-dot"></span>
            <span class="cfa-prog-lbl">{{ $e['court'] }}</span>
        </div>
    @endforeach
</div>
