@php
    use App\Models\Candidate;

    /** @var \App\Models\Candidate|null $candidate */

    $present = [];
    if ($candidate) {
        $present = $candidate->documents()->pluck('type')
            ->map(fn ($t) => $t instanceof \BackedEnum ? $t->value : (string) $t)->all();
    }
    $pieces = Candidate::piecesAttendues();
@endphp

@if (! $candidate)
    <div style="font-size:.82rem;color:var(--cfa-ink-soft)">Aucun apprenant rattaché.</div>
@else
    <div style="display:grid;gap:7px">
        @foreach ($pieces as $type)
            @php $ok = in_array($type->value, $present, true); @endphp
            <div style="display:flex;align-items:center;gap:9px;font-size:.84rem;color:var(--cfa-ink)">
                <span style="flex:none;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;{{ $ok ? 'background:color-mix(in srgb,#16a34a 15%,transparent);color:#16a34a' : 'background:color-mix(in srgb,#d97706 16%,transparent);color:#d97706' }}">{{ $ok ? '✓' : '!' }}</span>
                {{ $type->getLabel() }}
                <span style="margin-left:auto;font-size:.75rem;color:var(--cfa-ink-soft)">{{ $ok ? 'Fournie' : 'Manquante' }}</span>
            </div>
        @endforeach
    </div>
    <div style="margin-top:10px;font-size:.76rem;color:var(--cfa-ink-soft)">
        Le dépôt et la gestion des pièces se font dans la fiche de l'apprenant (les documents déjà fournis ne sont pas à redéposer).
    </div>
@endif
