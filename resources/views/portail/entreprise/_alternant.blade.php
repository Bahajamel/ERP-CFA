@php
    /** @var array{contract: \App\Models\Contract, candidate: \App\Models\Candidate, assiduite: array} $row */
    $c = $row['candidate'];
    $ct = $row['contract'];
    $a = $row['assiduite'];
    $detaille = $detaille ?? false;
    $taux = $a['taux'];
    $tauxCouleur = $taux === null ? 'text-slate-400'
        : ($taux >= 90 ? 'text-emerald-600' : ($taux >= 70 ? 'text-amber-600' : 'text-rose-600'));
@endphp
<div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-500">{{ $c->initiales }}</span>
    <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-slate-800">{{ $c->nom_complet }}</p>
        <p class="truncate text-xs text-slate-500">
            {{ $ct->formation?->libelle ?? $c->formationVisee?->libelle ?? '—' }}
            @if ($detaille && $ct->tuteur) · Tuteur : {{ $ct->tuteur->nom_complet }} @endif
        </p>
        @if ($detaille)
            @php $d = $ct->dateDebutEffective(); $f = $ct->dateFinEffective(); @endphp
            <p class="mt-0.5 text-xs text-slate-400">
                {{ $d ? $d->format('d/m/Y') : '—' }} → {{ $f ? $f->format('d/m/Y') : '—' }}
                @if ($ct->statut_contrat) · {{ $ct->statut_contrat->getLabel() }} @endif
            </p>
        @endif
    </div>
    <div class="shrink-0 text-right">
        <p class="text-base font-bold {{ $tauxCouleur }}">{{ $taux === null ? '—' : $taux.'%' }}</p>
        @if ($a['absences_injustifiees'] > 0)
            <p class="text-xs font-semibold text-rose-600">{{ $a['absences_injustifiees'] }} abs. injust.</p>
        @else
            <p class="text-xs text-slate-400">assiduité</p>
        @endif
    </div>
</div>