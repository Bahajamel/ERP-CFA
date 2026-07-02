@php
    /** @var \App\Models\Candidate $record */
    $lignes = $record->couvertureMissions();
    $taux = $record->tauxCouvertureMissions();
    $couvertes = $lignes->where('couverte', true)->count();
    $couleur = $taux >= 90 ? '#16a34a' : ($taux >= 60 ? '#d97706' : '#dc2626');
@endphp

<div style="display:flex;flex-direction:column;gap:0.75rem;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <span style="font-size:1.5rem;font-weight:700;color:{{ $couleur }};">{{ $taux }} %</span>
        <span style="color:#6b7280;">{{ $couvertes }} / {{ $lignes->count() }} missions couvertes par un livrable</span>
    </div>

    <p style="font-size:0.8rem;color:#6b7280;margin:0;">
        Missions du CFA (article L6231-2). Une mission est « couverte » dès qu'au moins un document
        de l'apprenti y est rattaché. Les documents se rattachent aux missions dans la GED
        (import LivretRS ou dépôt manuel).
    </p>

    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.25rem;">
        @foreach ($lignes as $ligne)
            <li style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.4rem 0.5rem;border-radius:0.375rem;background:{{ $ligne->couverte ? 'rgba(22,163,74,0.08)' : 'rgba(217,119,6,0.06)' }};">
                <span style="flex:none;font-weight:600;color:#6b7280;min-width:1.75rem;">{{ $ligne->mission->numero }}°</span>
                <span style="flex:none;">{{ $ligne->couverte ? '✅' : '⚠️' }}</span>
                <span style="flex:1;">
                    <span style="font-weight:600;">{{ $ligne->mission->titre }}</span>
                    <span style="display:block;font-size:0.75rem;color:{{ $ligne->couverte ? '#16a34a' : '#d97706' }};">
                        {{ $ligne->couverte ? 'Couverte' : 'Aucun livrable rattaché' }}
                    </span>
                </span>
            </li>
        @endforeach
    </ul>
</div>
