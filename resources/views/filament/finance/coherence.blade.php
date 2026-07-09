@php
    use App\Services\FinanceService;

    $score = $etat['score'];
    $scoreColor = $score >= 80 ? '#16a34a' : ($score >= 50 ? '#b45309' : '#dc2626');
@endphp

<div style="display:grid;gap:12px">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $scoreColor }}">{{ $score }}%</div>
            <div style="font-size:.8rem;color:#374151">Santé<br>du dossier</div>
        </div>
        <div style="flex:1;min-width:200px;height:9px;background:#eef0f4;border-radius:999px;overflow:hidden">
            <div style="width:{{ $score }}%;height:100%;background:{{ $scoreColor }}"></div>
        </div>
        <span style="font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:999px;color:#fff;background:{{ $scoreColor }}">
            {{ $etat['statut']->getLabel() }}
        </span>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @foreach ([['Finançable', $etat['facturable'], '#6b7280'], ['Facturé', $etat['facture'], '#2563eb'], ['Encaissé', $etat['encaisse'], '#16a34a']] as [$label, $montant, $couleur])
            <div style="flex:1;min-width:130px;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;background:#fff">
                <div style="font-size:.72rem;color:#6b7280">{{ $label }}</div>
                <div style="font-size:1rem;font-weight:700;color:{{ $couleur }}">{{ FinanceService::euros($montant) }}</div>
            </div>
        @endforeach
    </div>

    @if (! empty($etat['issues']))
        <div style="font-size:.8rem;color:#7f1d1d;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px">
            <div style="font-weight:700;margin-bottom:4px">{{ count($etat['issues']) }} point(s) à corriger :</div>
            <ul style="margin:0;padding-left:1.1rem;display:grid;gap:3px">
                @foreach ($etat['issues'] as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div style="font-size:.85rem;font-weight:600;color:#166534;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px">
            ✓ Dossier cohérent : montants alignés avec l'OPCO, facturation et encaissement à jour.
        </div>
    @endif
</div>
