@php
    use App\Services\ContractDocumentService;

    // Couleurs sémantiques adaptées clair/sombre : le texte reste lisible sur les
    // deux thèmes, le fond est une teinte translucide (color-mix) posée sur la carte.
    $badges = [
        ContractDocumentService::ETAT_GENERE => ['Généré · à jour', '#16a34a', 'color-mix(in srgb, #16a34a 16%, transparent)'],
        ContractDocumentService::ETAT_A_REGENERER => ['À régénérer', '#d97706', 'color-mix(in srgb, #d97706 18%, transparent)'],
        ContractDocumentService::ETAT_A_GENERER => ['À générer', 'var(--cfa-ink-soft)', 'color-mix(in srgb, var(--cfa-ink-soft) 16%, transparent)'],
    ];
    $score = $etat['score'];
    $scoreColor = $score >= 80 ? '#16a34a' : ($score >= 50 ? '#d97706' : '#dc2626');

    $carte = function (string $titre, array $doc) use ($badges) {
        [$label, $fg, $bg] = $badges[$doc['etat']];
        ob_start(); ?>
        <div style="flex:1;min-width:220px;border:1px solid var(--cfa-card-border);border-radius:8px;padding:12px 14px;background:var(--cfa-card-2)">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px">
                <b style="font-size:.9rem"><?= e($titre) ?></b>
                <span style="font-size:.72rem;font-weight:600;color:<?= $fg ?>;background:<?= $bg ?>;padding:2px 8px;border-radius:999px;white-space:nowrap"><?= e($label) ?></span>
            </div>
            <?php if ($doc['genere_le']): ?>
                <div style="font-size:.75rem;color:var(--cfa-ink-soft);margin-bottom:6px">Généré le <?= e($doc['genere_le']) ?></div>
            <?php endif; ?>
            <?php if (! empty($doc['manquants'])): ?>
                <div style="font-size:.75rem;color:#dc2626;font-weight:600;margin-bottom:2px"><?= count($doc['manquants']) ?> information(s) à compléter :</div>
                <ul style="margin:0;padding-left:1.1rem;font-size:.75rem;color:inherit;display:grid;gap:2px">
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
            <div style="font-size:.8rem;color:var(--cfa-ink-soft)">Complétude<br>du dossier</div>
        </div>
        <div style="flex:1;min-width:200px;height:9px;background:color-mix(in srgb, var(--cfa-ink-soft) 18%, transparent);border-radius:999px;overflow:hidden">
            <div style="width:{{ $score }}%;height:100%;background:{{ $scoreColor }}"></div>
        </div>
    </div>

    <div style="font-size:.85rem;font-weight:600;color:inherit;background:var(--cfa-card-2);border-left:3px solid {{ $scoreColor }};padding:8px 12px;border-radius:4px">
        {{ $etat['message'] }}
    </div>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
        {!! $carte('CERFA (contrat d\'apprentissage)', $etat['cerfa']) !!}
        {!! $carte('Convention de formation', $etat['convention']) !!}
    </div>

    @if (! empty($etat['cfa']['manquants']))
        <div style="font-size:.78rem;color:inherit;background:color-mix(in srgb, var(--cfa-accent) 12%, transparent);border:1px solid color-mix(in srgb, var(--cfa-accent) 35%, transparent);border-radius:8px;padding:10px 14px">
            <div style="font-weight:600;color:var(--cfa-accent);margin-bottom:4px">
                Identité du CFA à compléter — une seule fois, dans « Paramètres CFA »
            </div>
            <div style="color:var(--cfa-ink-soft);margin-bottom:6px">
                Ces informations ne se saisissent pas sur le contrat : elles valent pour tous vos dossiers.
            </div>
            <ul style="margin:0 0 8px;padding-left:1.1rem;display:grid;gap:2px">
                @foreach ($etat['cfa']['manquants'] as $m)
                    <li>{{ $m }}</li>
                @endforeach
            </ul>
            <a href="{{ $etat['cfa']['url'] }}"
               style="display:inline-block;font-weight:600;color:var(--cfa-accent);text-decoration:underline">
                Ouvrir les Paramètres CFA →
            </a>
        </div>
    @endif
</div>
