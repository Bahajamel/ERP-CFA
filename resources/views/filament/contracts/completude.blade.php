@php
    use App\Services\ContractDocumentService;

    $badges = [
        ContractDocumentService::ETAT_GENERE => ['Généré · à jour', '#16a34a', '#dcfce7'],
        ContractDocumentService::ETAT_A_REGENERER => ['À régénérer', '#b45309', '#fef3c7'],
        ContractDocumentService::ETAT_A_GENERER => ['À générer', '#6b7280', '#f3f4f6'],
    ];
    $score = $etat['score'];
    $scoreColor = $score >= 80 ? '#16a34a' : ($score >= 50 ? '#b45309' : '#dc2626');

    $carte = function (string $titre, array $doc) use ($badges) {
        [$label, $fg, $bg] = $badges[$doc['etat']];
        ob_start(); ?>
        <div style="flex:1;min-width:220px;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;background:#fff">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px">
                <b style="font-size:.9rem"><?= e($titre) ?></b>
                <span style="font-size:.72rem;font-weight:600;color:<?= $fg ?>;background:<?= $bg ?>;padding:2px 8px;border-radius:999px;white-space:nowrap"><?= e($label) ?></span>
            </div>
            <?php if ($doc['genere_le']): ?>
                <div style="font-size:.75rem;color:#6b7280;margin-bottom:6px">Généré le <?= e($doc['genere_le']) ?></div>
            <?php endif; ?>
            <?php if (! empty($doc['manquants'])): ?>
                <div style="font-size:.75rem;color:#b91c1c;font-weight:600;margin-bottom:2px"><?= count($doc['manquants']) ?> information(s) à compléter :</div>
                <ul style="margin:0;padding-left:1.1rem;font-size:.75rem;color:#374151;display:grid;gap:2px">
                    <?php foreach ($doc['manquants'] as $m): ?>
                        <li><?= e($m) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div style="font-size:.75rem;color:#16a34a;font-weight:600">✓ Toutes les informations nécessaires sont présentes.</div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    };
@endphp

<div style="display:grid;gap:12px">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $scoreColor }}">{{ $score }}%</div>
            <div style="font-size:.8rem;color:#374151">Complétude<br>du dossier</div>
        </div>
        <div style="flex:1;min-width:200px;height:9px;background:#eef0f4;border-radius:999px;overflow:hidden">
            <div style="width:{{ $score }}%;height:100%;background:{{ $scoreColor }}"></div>
        </div>
    </div>

    <div style="font-size:.85rem;font-weight:600;color:#1f2937;background:#f8fafc;border-left:3px solid {{ $scoreColor }};padding:8px 12px;border-radius:4px">
        {{ $etat['message'] }}
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
        {!! $carte('CERFA (contrat d\'apprentissage)', $etat['cerfa']) !!}
        {!! $carte('Convention de formation', $etat['convention']) !!}
    </div>

    @if (! empty($etat['cfa']['manquants']))
        <div style="font-size:.78rem;color:#374151;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px">
            <div style="font-weight:600;color:#1d4ed8;margin-bottom:4px">
                Identité du CFA à compléter — une seule fois, dans « Paramètres CFA »
            </div>
            <div style="color:#4b5563;margin-bottom:6px">
                Ces informations ne se saisissent pas sur le contrat : elles valent pour tous vos dossiers.
            </div>
            <ul style="margin:0 0 8px;padding-left:1.1rem;display:grid;gap:2px">
                @foreach ($etat['cfa']['manquants'] as $m)
                    <li>{{ $m }}</li>
                @endforeach
            </ul>
            <a href="{{ $etat['cfa']['url'] }}"
               style="display:inline-block;font-weight:600;color:#1d4ed8;text-decoration:underline">
                Ouvrir les Paramètres CFA →
            </a>
        </div>
    @endif
</div>
