<x-filament-panels::page>
    @php
        $euros = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ') . ' €';

        // Échelle du graphique : arrondi au palier de 25 000 € supérieur.
        $step = 25000;
        $chartMax = max($step, (int) (ceil(max((float) ($graphique['max'] ?? 0), 1) / $step) * $step));
        $yticks = [];
        for ($v = $chartMax; $v >= 0; $v -= $step) {
            $yticks[] = ['pct' => $v / $chartMax * 100, 'label' => $v === 0 ? '0 €' : number_format($v / 1000, 0, ',', ' ') . 'k €'];
        }
    @endphp

    <style>
        /* ===== Palette réutilisable (KPI, badges, points, barres) ===== */
        .fin-c-blue   { --c:#2563eb; --soft:#eff6ff; }
        .fin-c-teal   { --c:#0d9488; --soft:#f0fdfa; }
        .fin-c-green  { --c:#16a34a; --soft:#f0fdf4; }
        .fin-c-orange { --c:#ea580c; --soft:#fff7ed; }
        .fin-c-red    { --c:#dc2626; --soft:#fef2f2; }
        .fin-c-purple { --c:#7c3aed; --soft:#f5f3ff; }
        .fin-c-slate  { --c:#475569; --soft:#f1f5f9; }
        .fin-c-gray   { --c:#6b7280; --soft:#f3f4f6; }
        .dark .fin-c-blue{--soft:rgba(37,99,235,.16)} .dark .fin-c-teal{--soft:rgba(13,148,136,.16)}
        .dark .fin-c-green{--soft:rgba(22,163,74,.16)} .dark .fin-c-orange{--soft:rgba(234,88,12,.16)}
        .dark .fin-c-red{--soft:rgba(220,38,38,.16)} .dark .fin-c-purple{--soft:rgba(124,58,237,.16)}
        .dark .fin-c-slate{--soft:rgba(71,85,105,.22)} .dark .fin-c-gray{--soft:rgba(107,114,128,.22)}

        .fin { display: grid; gap: 1.25rem; }

        /* ===== Cartes ===== */
        .fin-card { background:#fff; border:1px solid #e9edf3; border-radius:16px; padding:1.15rem 1.3rem; box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .dark .fin-card { background:#0f172a; border-color:#1e293b; box-shadow:none; }
        .fin-card-h { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
        .fin-card-t { font-size:1rem; font-weight:800; color:#0f172a; }
        .dark .fin-card-t { color:#f8fafc; }
        .fin-link { font-size:.82rem; font-weight:600; color:#2563eb; text-decoration:none; white-space:nowrap; }
        .fin-link:hover { text-decoration:underline; }

        /* ===== Filtres ===== */
        .fin-filters { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.9rem; }
        .fin-field { display:flex; flex-direction:column; gap:.3rem; }
        .fin-field > label { font-size:.72rem; font-weight:600; color:#64748b; }
        .fin-field select, .fin-daterange {
            appearance:none; min-width:9.5rem; padding:.5rem .7rem; font-size:.85rem; border-radius:.6rem;
            border:1px solid #e2e8f0; background:#fff; color:#0f172a; cursor:pointer;
        }
        .dark .fin-field select, .dark .fin-daterange { border-color:#334155; background:#0b1220; color:#e2e8f0; }
        .fin-daterange { display:inline-flex; align-items:center; gap:.45rem; min-width:12.5rem; white-space:nowrap; }
        .fin-reset { margin-left:auto; display:inline-flex; align-items:center; gap:.4rem; padding:.55rem .85rem; font-size:.83rem; font-weight:600;
            border:1px solid #e2e8f0; border-radius:.6rem; background:#fff; color:#475569; cursor:pointer; }
        .dark .fin-reset { border-color:#334155; background:#0b1220; color:#cbd5e1; }
        .fin-ic { width:1rem; height:1rem; flex:none; }
        .fin-ic-4 { width:1.15rem; height:1.15rem; }

        /* ===== KPI ===== */
        .fin-kpis { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:1rem; }
        .fin-kpi { display:block; text-decoration:none; color:inherit; background:#fff; border:1px solid #e9edf3; border-radius:16px; padding:1.1rem 1.15rem; box-shadow:0 1px 2px rgba(16,24,40,.04); transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease; }
        .fin-kpi:hover { transform:translateY(-2px); border-color:var(--c); box-shadow:0 10px 24px -12px rgba(16,24,40,.25); }
        .dark .fin-kpi { background:#0f172a; border-color:#1e293b; box-shadow:none; }
        .dark .fin-kpi:hover { border-color:var(--c); box-shadow:0 10px 24px -10px rgba(0,0,0,.6); }
        .fin-kpi-top { display:flex; align-items:center; gap:.6rem; margin-bottom:.7rem; }
        .fin-kpi-ico { flex:none; display:grid; place-items:center; width:2.1rem; height:2.1rem; border-radius:10px; background:var(--soft); color:var(--c); }
        .fin-kpi-title { font-size:.8rem; font-weight:600; color:#64748b; line-height:1.2; }
        .fin-kpi-val { font-size:1.6rem; font-weight:800; letter-spacing:-.02em; color:#0f172a; }
        .dark .fin-kpi-val { color:#f8fafc; }
        .fin-kpi-desc { font-size:.78rem; color:#94a3b8; margin-top:.15rem; }
        .fin-kpi-var { display:inline-flex; align-items:center; gap:.25rem; margin-top:.7rem; font-size:.75rem; font-weight:600; color:var(--c); }

        /* ===== Grilles de sections ===== */
        .fin-2col { display:grid; grid-template-columns:1.15fr 1fr; gap:1.25rem; }
        .fin-3col { display:grid; grid-template-columns:1.35fr 1.1fr .95fr; gap:1.25rem; }
        /* min-width:0 : autorise les cellules à rétrécir → les tableaux (nowrap)
           défilent DANS leur carte (.fin-scroll) au lieu de déborder la page. */
        .fin, .fin-kpi, .fin-2col > *, .fin-3col > * { min-width:0; }

        /* ===== Tables ===== */
        .fin-scroll { overflow-x:auto; }
        table.fin-tbl { width:100%; border-collapse:collapse; }
        table.fin-tbl th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; color:#94a3b8; font-weight:600; padding:.5rem .6rem; border-bottom:1px solid #eef2f7; white-space:nowrap; }
        .dark table.fin-tbl th { border-color:#1e293b; }
        table.fin-tbl td { padding:.6rem .5rem; border-bottom:1px solid #f1f5f9; font-size:.82rem; color:#334155; }
        .dark table.fin-tbl td { border-color:#172033; color:#cbd5e1; }
        table.fin-tbl tr:last-child td { border-bottom:0; }
        .fin-strong { font-weight:700; color:#0f172a; }
        .dark .fin-strong { color:#f1f5f9; }
        .fin-end { text-align:right; white-space:nowrap; }

        .fin-dot { display:inline-block; width:.5rem; height:.5rem; border-radius:999px; background:var(--c); margin-right:.5rem; vertical-align:middle; }
        .fin-badge { display:inline-flex; align-items:center; padding:.15rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; background:var(--soft); color:var(--c); }

        .fin-btn-sm { display:inline-flex; align-items:center; padding:.3rem .7rem; font-size:.76rem; font-weight:600; border-radius:.5rem; border:1px solid #e2e8f0; background:#fff; color:#475569; cursor:pointer; text-decoration:none; }
        .dark .fin-btn-sm { border-color:#334155; background:#0b1220; color:#cbd5e1; }
        .fin-btn-alert { border-color:transparent; background:#fef2f2; color:#dc2626; }
        .dark .fin-btn-alert { background:rgba(220,38,38,.18); color:#fca5a5; }
        .fin-act-ico { display:inline-grid; place-items:center; width:1.9rem; height:1.9rem; border-radius:.5rem; color:#64748b; }
        .fin-act-ico:hover { background:#f1f5f9; color:#2563eb; }
        .dark .fin-act-ico:hover { background:#1e293b; }

        /* ===== Graphique en barres (CSS pur) ===== */
        .fin-chart { display:grid; grid-template-columns:auto 1fr; gap:.5rem; }
        .fin-plot { position:relative; height:290px; }
        .fin-gridline { position:absolute; left:0; right:0; border-top:1px dashed #eef2f7; }
        .dark .fin-gridline { border-color:#1e293b; }
        .fin-ytick { position:absolute; right:calc(100% + .5rem); transform:translateY(-50%); font-size:.68rem; color:#94a3b8; white-space:nowrap; }
        .fin-yaxis { width:3.2rem; }
        .fin-bars { position:absolute; inset:0; display:flex; align-items:flex-end; gap:.6rem; padding:0 .3rem; }
        .fin-barcol { flex:1; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; min-width:0; }
        .fin-barval { font-size:.72rem; font-weight:700; color:#334155; margin-bottom:.3rem; white-space:nowrap; }
        .dark .fin-barval { color:#cbd5e1; }
        .fin-bar { width:100%; max-width:3.2rem; border-radius:7px 7px 0 0; background:var(--c); opacity:.9; min-height:3px; }
        .fin-xlabels { display:flex; gap:.6rem; padding:.5rem .3rem 0; }
        .fin-xlabels > span { flex:1; text-align:center; font-size:.72rem; color:#64748b; min-width:0; }

        /* ===== Actions prioritaires ===== */
        .fin-actions { display:grid; gap:.65rem; }
        .fin-action { display:flex; align-items:flex-start; gap:.7rem; padding:.6rem .3rem; border-bottom:1px solid #f1f5f9; }
        .dark .fin-action { border-color:#172033; }
        .fin-action:last-child { border-bottom:0; }
        .fin-action-ico { flex:none; display:grid; place-items:center; width:2rem; height:2rem; border-radius:9px; background:#f1f5f9; color:#64748b; }
        .dark .fin-action-ico { background:#1e293b; color:#94a3b8; }
        .fin-action-body { flex:1; min-width:0; }
        .fin-action-t { font-size:.83rem; font-weight:600; color:#0f172a; line-height:1.3; }
        .dark .fin-action-t { color:#f1f5f9; }
        .fin-action-s { font-size:.74rem; color:#94a3b8; margin-top:.1rem; }
        .fin-action-date { flex:none; font-size:.7rem; font-weight:700; color:#475569; background:#f1f5f9; border-radius:999px; padding:.15rem .5rem; }
        .dark .fin-action-date { background:#1e293b; color:#cbd5e1; }

        /* ===== Responsive ===== */
        @media (max-width:1100px) {
            .fin-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); }
            .fin-2col, .fin-3col { grid-template-columns:1fr; }
        }
        @media (max-width:640px) {
            .fin-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .fin-filters { flex-direction:column; align-items:stretch; }
            .fin-reset { margin-left:0; }
        }
    </style>

    <div class="fin">
        {{-- ═══════════ Filtres (présentation — branchement à venir) ═══════════ --}}
        <div class="fin-card fin-filters">
            <div class="fin-field">
                <label>Période</label>
                <span class="fin-daterange">
                    <x-filament::icon icon="heroicon-o-calendar" class="fin-ic" />
                    01/01/2026 - 31/12/2026
                </span>
            </div>
            <div class="fin-field"><label>Formation</label><select><option>Toutes</option></select></div>
            <div class="fin-field"><label>Entreprise</label><select><option>Toutes</option></select></div>
            <div class="fin-field"><label>OPCO</label><select><option>Tous</option></select></div>
            <div class="fin-field"><label>Statut financier</label><select><option>Tous</option></select></div>
            <div class="fin-field"><label>Responsable</label><select><option>Tous</option></select></div>
            <button type="button" class="fin-reset">
                <x-filament::icon icon="heroicon-o-arrow-path" class="fin-ic" /> Réinitialiser
            </button>
        </div>

        {{-- ═══════════ Cartes KPI ═══════════ --}}
        <div class="fin-kpis">
            @foreach ($kpis as $k)
                <a href="{{ $k['zone'] === 'contrats' ? $contratsUrl : $opcoUrl }}" class="fin-kpi fin-c-{{ $k['couleur'] }}">
                    <div class="fin-kpi-top">
                        <span class="fin-kpi-ico"><x-filament::icon :icon="$k['icon']" class="fin-ic-4" /></span>
                        <span class="fin-kpi-title">{{ $k['label'] }}</span>
                    </div>
                    <div class="fin-kpi-val">{{ $k['unite'] === '€' ? $euros($k['valeur']) : number_format($k['valeur'], 0, ',', ' ') }}</div>
                    <div class="fin-kpi-desc">{{ $k['description'] }}</div>
                    <div class="fin-kpi-var">
                        <x-filament::icon icon="heroicon-m-arrow-trending-up" class="fin-ic" />
                        {{ $k['variation'] }} vs période précédente
                    </div>
                </a>
            @endforeach
        </div>

        {{-- ═══════════ Cash bloqué + Suivi financier ═══════════ --}}
        <div class="fin-2col">
            {{-- Cash bloqué par raison (DÉMO) --}}
            <div class="fin-card">
                <div class="fin-card-h">
                    <span class="fin-card-t">Cash bloqué par raison</span>
                    <a href="{{ $opcoUrl }}" class="fin-link">Voir tous les dossiers</a>
                </div>
                <div class="fin-scroll">
                    <table class="fin-tbl">
                        <thead><tr>
                            <th>Raison du blocage</th><th class="fin-end">Montant bloqué</th>
                            <th class="fin-end">Dossiers</th><th>Responsable</th><th>Action</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($cashBloque as $r)
                                <tr>
                                    <td><span class="fin-dot fin-c-{{ $r['couleur'] }}"></span>{{ $r['raison'] }}</td>
                                    <td class="fin-end fin-strong">{{ $euros($r['montant']) }}</td>
                                    <td class="fin-end">{{ $r['dossiers'] }}</td>
                                    <td>{{ $r['responsable'] }}</td>
                                    <td>
                                        <a href="{{ $r['zone'] === 'contrats' ? $contratsUrl : $opcoUrl }}" class="fin-btn-sm {{ $r['action'] === 'relancer' ? 'fin-btn-alert' : '' }}">
                                            {{ $r['action'] === 'relancer' ? 'Relancer' : 'Voir' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Suivi financier (barres CSS) --}}
            <div class="fin-card">
                <div class="fin-card-h">
                    <span class="fin-card-t">Suivi financier</span>
                    <select class="fin-daterange" style="min-width:auto"><option>Par montant</option></select>
                </div>
                <div class="fin-chart">
                    <div class="fin-yaxis"></div>
                    <div class="fin-plot">
                        @foreach ($yticks as $t)
                            <div class="fin-gridline" style="bottom:{{ $t['pct'] }}%">
                                <span class="fin-ytick">{{ $t['label'] }}</span>
                            </div>
                        @endforeach
                        <div class="fin-bars">
                            @foreach ($graphique['bars'] as $b)
                                <div class="fin-barcol">
                                    <span class="fin-barval">{{ $euros($b['valeur']) }}</span>
                                    <div class="fin-bar fin-c-{{ $b['couleur'] }}" style="height:{{ round($b['valeur'] / $chartMax * 100, 1) }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="fin-chart">
                    <div class="fin-yaxis"></div>
                    <div class="fin-xlabels">
                        @foreach ($graphique['bars'] as $b)<span>{{ $b['label'] }}</span>@endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════ Factures / Paiements / Actions ═══════════ --}}
        <div class="fin-3col">
            {{-- Factures récentes --}}
            <div class="fin-card">
                <div class="fin-card-h">
                    <span class="fin-card-t">Factures récentes</span>
                    <a href="{{ $opcoUrl }}" class="fin-link">Voir toutes les factures</a>
                </div>
                <div class="fin-scroll">
                    <table class="fin-tbl">
                        <thead><tr>
                            <th>Facture</th><th>Contrat</th><th>Entreprise</th><th>OPCO</th>
                            <th class="fin-end">Montant</th><th>Échéance</th><th>Statut</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($factures as $f)
                                <tr>
                                    <td class="fin-strong">{{ $f['facture'] }}</td>
                                    <td>{{ $f['contrat'] }}</td>
                                    <td>{{ $f['entreprise'] }}</td>
                                    <td>{{ $f['opco'] }}</td>
                                    <td class="fin-end fin-strong">{{ $euros($f['montant']) }}</td>
                                    <td>{{ $f['echeance'] }}</td>
                                    <td><span class="fin-badge fin-c-{{ $f['statut']['color'] }}">{{ $f['statut']['label'] }}</span></td>
                                    <td>
                                        <a href="{{ $opcoUrl }}" class="fin-act-ico" title="Voir">
                                            <x-filament::icon icon="heroicon-o-eye" class="fin-ic" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Paiements récents --}}
            <div class="fin-card">
                <div class="fin-card-h">
                    <span class="fin-card-t">Paiements récents</span>
                    <a href="{{ $opcoUrl }}" class="fin-link">Voir tous les paiements</a>
                </div>
                <div class="fin-scroll">
                    <table class="fin-tbl">
                        <thead><tr>
                            <th>Date</th><th>Facture</th><th>Payeur</th><th class="fin-end">Montant reçu</th><th>Statut</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($paiements as $p)
                                <tr>
                                    <td>{{ $p['date'] }}</td>
                                    <td class="fin-strong">{{ $p['facture'] }}</td>
                                    <td>{{ $p['payeur'] }}</td>
                                    <td class="fin-end fin-strong">{{ $euros($p['montant']) }}</td>
                                    <td><span class="fin-badge fin-c-{{ $p['statut']['color'] }}">{{ $p['statut']['label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Actions prioritaires --}}
            <div class="fin-card">
                <div class="fin-card-h"><span class="fin-card-t">Actions prioritaires</span></div>
                <div class="fin-actions">
                    @foreach ($actions as $a)
                        <div class="fin-action">
                            <span class="fin-action-ico"><x-filament::icon :icon="$a['icon']" class="fin-ic" /></span>
                            <div class="fin-action-body">
                                <div class="fin-action-t">{{ $a['titre'] }}</div>
                                <div class="fin-action-s">{{ $a['soustexte'] }}</div>
                            </div>
                            <span class="fin-action-date">{{ $a['date'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div style="text-align:center;margin-top:.9rem">
                    <a href="{{ $opcoUrl }}" class="fin-link">Voir toutes les actions</a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
