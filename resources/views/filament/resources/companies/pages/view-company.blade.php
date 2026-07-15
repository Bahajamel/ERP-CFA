@php
    use App\Filament\Resources\Companies\CompanyResource;
    use App\Filament\Resources\Needs\NeedResource;

    $record = $this->getRecord();
    $badge = ['gray' => 'fc-b-gray', 'info' => 'fc-b-info', 'primary' => 'fc-b-info', 'warning' => 'fc-b-warn', 'danger' => 'fc-b-danger', 'success' => 'fc-b-success'];
    $bcls = fn ($enum) => $badge[$enum?->getColor() ?? 'gray'] ?? 'fc-b-gray';

    $adresse = trim(implode(' ', array_filter([$record->adresse, $record->code_postal, $record->ville])));
    $sat = $kpis['satisfaction'];
    $satCol = $sat === null ? 'gray' : ($sat >= 4 ? 'success' : ($sat === 3 ? 'warning' : 'danger'));
    $relancePast = $relance && $relance->prochaine_action_le && $relance->prochaine_action_le->isPast();
@endphp

<div class="fc-page">
    <x-filament-actions::modals />
    <style>
        .fc-page { --fc-line: rgba(15,23,42,.08); display: flex; flex-direction: column; gap: 1rem; }
        .dark .fc-page { --fc-line: rgba(255,255,255,.09); }
        .fc-card { background: #fff; border: 1px solid var(--fc-line); border-radius: 1rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .dark .fc-card { background: #18202f; }
        .fc-cardhead { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .85rem 1.1rem; border-bottom: 1px solid var(--fc-line); }
        .fc-cardhead h3 { font-weight: 700; font-size: .95rem; color: #0f172a; display: flex; align-items: center; gap: .5rem; }
        .dark .fc-cardhead h3 { color: #f1f5f9; }
        .fc-cardhead h3 svg { width: 1.1rem; height: 1.1rem; color: #64748b; }
        .fc-cardhead .count { font-size: .75rem; color: #94a3b8; font-weight: 600; }
        .fc-cardbody { padding: 1.1rem; }
        .fc-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
        .dark .fc-title { color: #f8fafc; }
        .fc-subtitle { font-size: .85rem; color: #64748b; margin-top: .1rem; }

        /* En-tête */
        .fc-header { display: flex; flex-wrap: wrap; gap: 1.3rem; align-items: flex-start; padding: 1.3rem; }
        .fc-avatar { width: 5rem; height: 5rem; border-radius: 1rem; display: grid; place-items: center; flex: none;
            background: linear-gradient(135deg,#0ea5e9,#2563eb); color: #fff; font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; }
        .fc-h-main { flex: 1; min-width: 260px; }
        .fc-h-name { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; }
        .fc-h-name .n { font-size: 1.6rem; font-weight: 800; color: #0f172a; } .dark .fc-h-name .n { color: #f8fafc; }
        .fc-h-info { margin-top: .9rem; display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .8rem 1.5rem; }
        .fc-h-item { display: flex; gap: .55rem; align-items: flex-start; }
        .fc-h-item svg { width: 1.05rem; height: 1.05rem; color: #94a3b8; margin-top: .15rem; flex: none; }
        .fc-h-item .l { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; }
        .fc-h-item .v { font-size: .85rem; color: #1e293b; font-weight: 500; } .dark .fc-h-item .v { color: #e2e8f0; }
        .fc-h-actions { display: flex; flex-direction: column; gap: .45rem; align-items: stretch; min-width: 170px; }

        .fc-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; }
        .fc-b-gray { background: #f1f5f9; color: #475569; } .dark .fc-b-gray { background: #334155; color: #cbd5e1; }
        .fc-b-info { background: #dbeafe; color: #1d4ed8; } .dark .fc-b-info { background: rgba(37,99,235,.25); color: #93c5fd; }
        .fc-b-warn { background: #ffedd5; color: #c2410c; } .dark .fc-b-warn { background: rgba(234,88,12,.22); color: #fdba74; }
        .fc-b-danger { background: #fee2e2; color: #b91c1c; } .dark .fc-b-danger { background: rgba(239,68,68,.2); color: #fca5a5; }
        .fc-b-success { background: #dcfce7; color: #15803d; } .dark .fc-b-success { background: rgba(34,197,94,.2); color: #86efac; }

        .fc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .5rem .85rem; border-radius: .6rem;
            font-size: .82rem; font-weight: 600; border: 1px solid var(--fc-line); background: #fff; color: #334155; cursor: pointer; text-decoration: none; }
        .dark .fc-btn { background: #18202f; color: #cbd5e1; } .fc-btn:hover { background: #f8fafc; } .dark .fc-btn:hover { background: #1f2937; }
        .fc-btn svg { width: 1rem; height: 1rem; }
        .fc-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; } .fc-btn--primary:hover { background: #1d4ed8; }

        /* KPI */
        .fc-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .8rem; }
        .fc-kpi { display: flex; align-items: center; gap: .8rem; padding: .9rem 1rem; }
        .fc-kpi-ic { width: 2.5rem; height: 2.5rem; border-radius: 9999px; display: grid; place-items: center; flex: none; }
        .fc-kpi-ic svg { width: 1.25rem; height: 1.25rem; }
        .fc-kpi .val { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1; } .dark .fc-kpi .val { color: #f8fafc; }
        .fc-kpi .lbl { font-size: .76rem; color: #64748b; margin-top: .15rem; }
        .fc-ic-blue { background: #dbeafe; color: #2563eb; } .dark .fc-ic-blue { background: rgba(37,99,235,.2); }
        .fc-ic-violet { background: #ede9fe; color: #7c3aed; } .dark .fc-ic-violet { background: rgba(124,58,237,.2); }
        .fc-ic-green { background: #dcfce7; color: #16a34a; } .dark .fc-ic-green { background: rgba(34,197,94,.2); }
        .fc-ic-amber { background: #fef3c7; color: #d97706; } .dark .fc-ic-amber { background: rgba(217,119,6,.2); }

        /* Grille */
        .fc-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: start; }
        .fc-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }

        .fc-fields { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .9rem 1.5rem; }
        .fc-field .l { font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; }
        .fc-field .v { font-size: .88rem; color: #1e293b; font-weight: 500; margin-top: .15rem; } .dark .fc-field .v { color: #e2e8f0; }
        .fc-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .2rem; }

        /* Table */
        .fc-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .fc-table th { text-align: left; padding: .5rem .6rem; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; }
        .fc-table td { padding: .6rem; border-top: 1px solid var(--fc-line); color: #1e293b; } .dark .fc-table td { color: #e2e8f0; }
        .fc-tablewrap { overflow-x: auto; }

        /* Sidebar rows */
        .fc-adm { display: flex; flex-direction: column; gap: .1rem; }
        .fc-adm-row { display: flex; align-items: center; gap: .6rem; padding: .55rem 0; border-bottom: 1px dashed var(--fc-line); }
        .fc-adm-row:last-child { border-bottom: none; }
        .fc-adm-row .ic { width: 1.6rem; height: 1.6rem; border-radius: .45rem; display: grid; place-items: center; flex: none; }
        .fc-adm-row .ic svg { width: .95rem; height: .95rem; }
        .fc-adm-row .lbl { font-size: .82rem; color: #475569; } .dark .fc-adm-row .lbl { color: #cbd5e1; }
        .fc-adm-row .val { margin-left: auto; font-size: .82rem; font-weight: 700; }

        .fc-note { padding: .6rem .75rem; background: #f8fafc; border: 1px solid var(--fc-line); border-radius: .6rem; margin-bottom: .5rem; }
        .dark .fc-note { background: #0f1725; }
        .fc-note .c { font-size: .82rem; color: #334155; } .dark .fc-note .c { color: #cbd5e1; }
        .fc-note .m { font-size: .7rem; color: #94a3b8; margin-top: .3rem; }
        .fc-empty { color: #94a3b8; font-size: .82rem; padding: .5rem 0; }

        @media (max-width: 1024px) { .fc-grid { grid-template-columns: 1fr; } .fc-kpis { grid-template-columns: repeat(2,1fr); } .fc-h-info { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 640px) { .fc-fields { grid-template-columns: 1fr; } .fc-h-info { grid-template-columns: 1fr; } .fc-h-actions { width: 100%; } }
    </style>

    <div>
        <div class="fc-title">Fiche entreprise</div>
        <div class="fc-subtitle">Vue 360° du partenaire</div>
    </div>

    {{-- En-tête --}}
    <div class="fc-card fc-header">
        <span class="fc-avatar">{{ $record->initiales }}</span>
        <div class="fc-h-main">
            <div class="fc-h-name">
                <span class="n">{{ $record->raison_sociale }}</span>
                <span class="fc-badge {{ $bcls($record->statut) }}">{{ $record->statut?->getLabel() }}</span>
            </div>
            <div class="fc-h-info">
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01M9 14h.01M15 14h.01"/></svg><div><div class="l">Secteur</div><div class="v">{{ $record->secteur ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg><div><div class="l">SIRET</div><div class="v">{{ $record->siret ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg><div><div class="l">OPCO</div><div class="v">{{ $record->opco?->nom ?? '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><div><div class="l">Adresse</div><div class="v">{{ $adresse ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg><div><div class="l">Contact principal</div><div class="v">{{ $contactPrincipal ? $contactPrincipal->nom_complet.($contactPrincipal->fonction ? ' · '.$contactPrincipal->fonction : '') : '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg><div><div class="l">Email</div><div class="v">{{ $contactPrincipal?->email ?: '—' }}</div></div></div>
            </div>
        </div>
        <div class="fc-h-actions">
            <a href="{{ NeedResource::getUrl('create') }}" class="fc-btn fc-btn--primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg> Nouveau besoin</a>
            @if ($besoins->isNotEmpty())
                <a href="{{ NeedResource::getUrl('index') }}" class="fc-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10M7 17 17 7"/></svg> Voir les besoins</a>
            @endif
            <a href="{{ CompanyResource::getUrl('edit', ['record' => $record]) }}" class="fc-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg> Modifier</a>
        </div>
    </div>

    {{-- KPI --}}
    <div class="fc-kpis">
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9m9 5h-9m9 5h-9M4 7h.01M4 12h.01M4 17h.01"/></svg></span><div><div class="val">{{ $kpis['besoinsOuverts'] }}</div><div class="lbl">Besoins ouverts</div></div></div>
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-violet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg></span><div><div class="val">{{ $kpis['candidats'] }}</div><div class="lbl">Candidats proposés</div></div></div>
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg></span><div><div class="val">{{ $kpis['contrats'] }}</div><div class="lbl">Contrats</div></div></div>
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 6.9 7.6.6-5.8 4.9 1.8 7.4L12 18l-6.6 3.8 1.8-7.4L1.4 9.5 9 8.9z"/></svg></span><div><div class="val">{{ $sat !== null ? $sat.'/5' : '—' }}</div><div class="lbl">Satisfaction</div></div></div>
    </div>

    <div class="fc-grid">
        {{-- Colonne principale --}}
        <div class="fc-col">
            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01"/></svg> Identité & activité</h3></div>
                <div class="fc-cardbody">
                    <div class="fc-fields">
                        <div class="fc-field"><div class="l">Nom commercial</div><div class="v">{{ $record->nom_commercial ?: '—' }}</div></div>
                        <div class="fc-field"><div class="l">SIRET</div><div class="v">{{ $record->siret ?: '—' }}</div></div>
                        <div class="fc-field"><div class="l">Secteur</div><div class="v">{{ $record->secteur ?: '—' }}</div></div>
                        <div class="fc-field"><div class="l">OPCO</div><div class="v">{{ $record->opco?->nom ?? '—' }}</div></div>
                        <div class="fc-field"><div class="l">Adresse</div><div class="v">{{ $adresse ?: '—' }}</div></div>
                        <div class="fc-field"><div class="l">Formations recherchées</div>
                            @if ($formationsRecherchees->isEmpty())
                                <div class="v">—</div>
                            @else
                                <div class="fc-chips">@foreach ($formationsRecherchees as $f)<span class="fc-badge fc-b-info">{{ $f }}</span>@endforeach</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9m9 5h-9m9 5h-9M4 7h.01M4 12h.01M4 17h.01"/></svg> Besoins de recrutement</h3><span class="count">{{ $besoins->count() }}</span></div>
                <div class="fc-cardbody">
                    @if ($besoins->isEmpty())
                        <div class="fc-empty">Aucun besoin enregistré pour cette entreprise.</div>
                    @else
                        <div class="fc-tablewrap">
                            <table class="fc-table">
                                <thead><tr><th>Poste</th><th>Formation</th><th>Statut</th><th>Postes restants</th></tr></thead>
                                <tbody>
                                    @foreach ($besoins as $b)
                                        <tr>
                                            <td style="font-weight:600">{{ $b['poste'] }}</td>
                                            <td>{{ $b['formation'] }}</td>
                                            <td><span class="fc-badge {{ $bcls($b['statut']) }}">{{ $b['statut']?->getLabel() }}</span></td>
                                            <td>{{ $b['restants'] }} / {{ $b['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg> Candidats proposés</h3><span class="count">{{ $candidats->count() }}</span></div>
                <div class="fc-cardbody">
                    @if ($candidats->isEmpty())
                        <div class="fc-empty">Aucun candidat proposé.</div>
                    @else
                        <div class="fc-tablewrap">
                            <table class="fc-table">
                                <thead><tr><th>Candidat</th><th>Besoin</th><th>Statut</th><th>Date</th></tr></thead>
                                <tbody>
                                    @foreach ($candidats as $m)
                                        <tr>
                                            <td style="font-weight:600">{{ $m['candidat'] }}</td>
                                            <td>{{ $m['besoin'] }}</td>
                                            <td><span class="fc-badge {{ $bcls($m['statut']) }}">{{ $m['statut']?->getLabel() }}</span></td>
                                            <td style="color:#94a3b8">{{ $m['date']?->format('d/m/Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/></svg> Contrats</h3><span class="count">{{ $contrats->count() }}</span></div>
                <div class="fc-cardbody">
                    @if ($contrats->isEmpty())
                        <div class="fc-empty">Aucun contrat.</div>
                    @else
                        <div class="fc-tablewrap">
                            <table class="fc-table">
                                <thead><tr><th>Apprenti</th><th>Statut</th><th>Période</th><th>OPCO</th></tr></thead>
                                <tbody>
                                    @foreach ($contrats as $ct)
                                        <tr>
                                            <td style="font-weight:600">{{ $ct['apprenti'] }}</td>
                                            <td><span class="fc-badge {{ $bcls($ct['statut']) }}">{{ $ct['statut']?->getLabel() }}</span></td>
                                            <td>{{ $ct['periode'] }}</td>
                                            <td>@if($ct['opco'])<span class="fc-badge {{ $bcls($ct['opco']) }}">{{ $ct['opco']?->getLabel() }}</span>@else <span style="color:#94a3b8">—</span> @endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Colonne latérale --}}
        <div class="fc-col">
            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Suivi commercial</h3></div>
                <div class="fc-cardbody">
                    @php
                        $suivi = [
                            ['Satisfaction', $sat !== null ? $sat.'/5' : '—', $satCol],
                            ['Prochaine relance', $relance?->prochaine_action_le?->format('d/m/Y') ?? 'Aucune', $relancePast ? 'danger' : ($relance ? 'info' : 'gray')],
                            ['Incidents', (string) $incidents, $incidents > 0 ? 'danger' : 'success'],
                            ['Besoins ouverts', (string) $kpis['besoinsOuverts'], $kpis['besoinsOuverts'] > 0 ? 'info' : 'gray'],
                            ['Contrats', (string) $kpis['contrats'], $kpis['contrats'] > 0 ? 'success' : 'gray'],
                        ];
                        $hex = ['success' => '#16a34a', 'danger' => '#dc2626', 'warning' => '#d97706', 'info' => '#2563eb', 'gray' => '#64748b'];
                    @endphp
                    <div class="fc-adm">
                        @foreach ($suivi as [$lbl, $val, $col])
                            <div class="fc-adm-row">
                                <span class="ic {{ $badge[$col] ?? 'fc-b-gray' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">@if($col==='success')<path d="M20 6 9 17l-5-5"/>@elseif($col==='danger')<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>@else<circle cx="12" cy="12" r="10"/>@endif</svg></span>
                                <span class="lbl">{{ $lbl }}</span>
                                <span class="val" style="color: {{ $hex[$col] ?? '#64748b' }}">{{ $val }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($relance && $relance->resume)
                        <div style="margin-top:.7rem; font-size:.78rem; color:#64748b;">
                            <span style="font-weight:600; color:{{ $relancePast ? '#dc2626' : '#2563eb' }}">Relance :</span> {{ $relance->resume }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Commentaires internes</h3><span class="count">{{ $notes->count() }}</span></div>
                <div class="fc-cardbody">
                    @forelse ($notes as $n)
                        <div class="fc-note"><div class="c">{{ $n->contenu }}</div><div class="m">{{ $n->author?->name ?? 'Système' }} — {{ $n->created_at?->format('d/m/Y H:i') }}</div></div>
                    @empty
                        <div class="fc-empty">Aucun commentaire interne.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Contacts & notes (RelationManagers existants) --}}
    <div class="fc-card">
        <div class="fc-cardbody">
            @livewire(\App\Filament\Resources\Companies\RelationManagers\ContactsRelationManager::class, ['ownerRecord' => $record, 'pageClass' => $this::class], key('fc-contacts-'.$record->getKey()))
        </div>
    </div>
    <div class="fc-card">
        <div class="fc-cardbody">
            @livewire(\App\Filament\RelationManagers\NotesRelationManager::class, ['ownerRecord' => $record, 'pageClass' => $this::class], key('fc-notes-'.$record->getKey()))
        </div>
    </div>
</div>
