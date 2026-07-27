<x-filament-widgets::widget>
    @php
        // ---- Palette sémantique (accents du cockpit) --------------------
        $tones = [
            'info' => '#3b82f6', 'warning' => '#f59e0b', 'success' => '#10b981',
            'danger' => '#f43f5e', 'violet' => '#8b5cf6', 'turquoise' => '#14b8a6',
            'primary' => '#6366f1',
        ];

        // ---- Icônes (tracés heroicon inline) ----------------------------
        $paths = [
            'user-group' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
            'briefcase' => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z',
            'check-badge' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'banknotes' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'bell-alert' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
            'chart-bar' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            'paper-airplane' => 'M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5',
            'folder-open' => 'M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776',
            'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
            'pencil' => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125',
            'sparkles' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z',
            'cpu' => 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z',
            'exclamation' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
            'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'bolt' => 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
            'light-bulb' => 'M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
            'flag' => 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5',
        ];

        $icon = function (string $name) use ($paths): string {
            $d = $paths[$name] ?? $paths['clock'];
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
                .'stroke-linecap="round" stroke-linejoin="round"><path d="'.$d.'"/></svg>';
        };

        // ---- Mini-courbe (sparkline aire) -------------------------------
        $spark = function (array $s, string $color): string {
            $s = array_values(array_map('floatval', $s));
            if (count($s) < 2) { $s = [0, 0]; }
            $w = 120; $h = 36; $p = 3;
            $min = min($s); $max = max($s); $range = ($max - $min) ?: 1;
            $n = count($s); $step = ($w - 2 * $p) / ($n - 1);
            $pts = [];
            foreach ($s as $i => $v) {
                $x = $p + $i * $step;
                $y = $h - $p - (($v - $min) / $range) * ($h - 2 * $p);
                $pts[] = round($x, 1).','.round($y, 1);
            }
            $line = 'M '.implode(' L ', $pts);
            $lastX = round($p + ($n - 1) * $step, 1);
            $area = $line.' L '.$lastX.','.($h - $p).' L '.$p.','.($h - $p).' Z';
            $id = 'sp'.substr(md5($color.$line), 0, 6);
            return "<svg class='cfa-spark' viewBox='0 0 $w $h' preserveAspectRatio='none' aria-hidden='true'>"
                ."<defs><linearGradient id='$id' x1='0' y1='0' x2='0' y2='1'>"
                ."<stop offset='0' stop-color='$color' stop-opacity='0.28'/>"
                ."<stop offset='1' stop-color='$color' stop-opacity='0'/></linearGradient></defs>"
                ."<path d='$area' fill='url(#$id)'/>"
                ."<path d='$line' fill='none' stroke='$color' stroke-width='1.8'/></svg>";
        };

        // ---- Graphe lignes (évolution) ----------------------------------
        $lineChart = function (array $labels, array $series): string {
            $w = 360; $h = 150; $p = 10;
            $all = [];
            foreach ($series as $s) { $all = array_merge($all, $s['data']); }
            $max = (max($all ?: [0]) ?: 1);
            $n = max(count($labels), 1);
            $step = ($w - 2 * $p) / max($n - 1, 1);
            $svg = "<svg class='cfa-line' viewBox='0 0 $w $h' aria-hidden='true'>";
            for ($g = 0; $g <= 3; $g++) {
                $y = round($p + ($h - 2 * $p) * $g / 3, 1);
                $svg .= "<line x1='$p' y1='$y' x2='".($w - $p)."' y2='$y' class='cfa-line-grid'/>";
            }
            foreach ($series as $s) {
                $pts = [];
                foreach ($s['data'] as $i => $v) {
                    $x = $p + $i * $step;
                    $y = $h - $p - ($v / $max) * ($h - 2 * $p);
                    $pts[] = round($x, 1).','.round($y, 1);
                }
                if (count($pts) < 2) { continue; }
                $svg .= "<path d='M ".implode(' L ', $pts)."' fill='none' stroke='{$s['color']}' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/>";
                foreach ($pts as $pt) {
                    [$x, $y] = explode(',', $pt);
                    $svg .= "<circle cx='$x' cy='$y' r='2.6' fill='{$s['color']}'/>";
                }
            }
            return $svg.'</svg>';
        };

        // ---- Anneau (répartition) ---------------------------------------
        $donut = function (array $segments, int $total): string {
            $cx = 70; $cy = 70; $r = 54; $sw = 18; $c = 2 * M_PI * $r;
            $svg = "<svg class='cfa-donut' viewBox='0 0 140 140' aria-hidden='true'>";
            $svg .= "<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='var(--cfa-card-border)' stroke-width='$sw'/>";
            $offset = 0;
            if ($total > 0) {
                foreach ($segments as $seg) {
                    if ($seg['valeur'] <= 0) { continue; }
                    $len = $seg['valeur'] / $total * $c;
                    $svg .= "<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='{$seg['color']}' stroke-width='$sw' "
                        ."stroke-dasharray='".round($len, 2).' '.round($c - $len, 2)."' "
                        ."stroke-dashoffset='".round(-$offset, 2)."' transform='rotate(-90 $cx $cy)'/>";
                    $offset += $len;
                }
            }
            return $svg.'</svg>';
        };

        $trendClass = fn (int $t): string => $t > 0 ? 'up' : ($t < 0 ? 'down' : 'flat');
    @endphp

    <div class="cfa-cockpit">

        {{-- ============ EN-TÊTE ============ --}}
        <div class="cfa-ck-head">
            <div>
                <h1 class="cfa-ck-title">Vue globale</h1>
                <p class="cfa-ck-sub">Pilotage en temps réel de votre activité</p>
            </div>
            <div class="cfa-ck-date">
                <span class="cfa-ck-ico">{!! $icon('calendar') !!}</span>
                {{ ucfirst(now()->translatedFormat('l j F Y')) }}
            </div>
        </div>

        {{-- ============ FILTRES PAR DÉPARTEMENT ============ --}}
        {{-- Chaque onglet filtre les KPI sur son service (« Vue globale » = tous),
             sans quitter le tableau de bord. --}}
        <nav class="cfa-ck-tabs" aria-label="Filtrer les indicateurs par département">
            @foreach ($departements as $tab)
                <button type="button" wire:click="definirService('{{ $tab['service'] }}')"
                        class="cfa-ck-tab {{ $tab['actif'] ? 'actif' : '' }}"
                        @if($tab['actif']) aria-current="true" @endif>
                    <span class="cfa-ck-tab-ico">{!! $icon($tab['icon']) !!}</span>
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>

        {{-- ============ SUPERVISION INTELLIGENTE ============ --}}
        <section class="cfa-ck-insights">
            <div class="cfa-ins-grid">
                <div class="cfa-ins-title-wrap">
                    <div class="cfa-ins-title">
                        <span class="cfa-ins-spark">{!! $icon('sparkles') !!}</span>
                        Supervision intelligente
                        <span class="cfa-ins-badge">Résumé du jour</span>
                    </div>
                    <p class="cfa-ins-retenir">{{ $insights['a_retenir'] }}</p>
                </div>

                <div class="cfa-ins-col">
                    <div class="cfa-ins-col-h"><span class="cfa-dot danger"></span>Anomalies détectées</div>
                    <ul>
                        @foreach ($insights['anomalies'] as $a)<li>{{ $a }}</li>@endforeach
                    </ul>
                </div>

                <div class="cfa-ins-col">
                    <div class="cfa-ins-col-h"><span class="cfa-dot success"></span>Recommandations</div>
                    <ul>
                        @foreach ($insights['recommandations'] as $r)<li>{{ $r }}</li>@endforeach
                    </ul>
                </div>

                <div class="cfa-ins-col">
                    <div class="cfa-ins-col-h"><span class="cfa-dot info"></span>Actions prioritaires</div>
                    <ol>
                        @foreach ($insights['actions'] as $ac)<li>{{ $ac }}</li>@endforeach
                    </ol>
                </div>

                <div class="cfa-ins-ai">
                    <span class="cfa-ai-orb">{!! $icon('cpu') !!}</span>
                    <div class="cfa-ai-txt">Besoin d'aide ?</div>
                    <div class="cfa-ai-hint">Synthèse calculée en direct sur vos données.</div>
                    <button type="button" class="cfa-ai-btn" wire:click="demanderIA" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="demanderIA">Demander à l'IA</span>
                        <span wire:loading wire:target="demanderIA">Analyse…</span>
                    </button>
                </div>
            </div>
        </section>

        {{-- ============ CARTES KPI ============ --}}
        <section class="cfa-ck-kpis">
            @foreach ($kpis as $k)
                @php $col = $tones[$k['tone']] ?? $tones['info']; $tc = $trendClass($k['trend']); @endphp
                <{{ $k['url'] ? 'a' : 'div' }} @if($k['url']) href="{{ $k['url'] }}" @endif
                    class="cfa-kpi" style="--kpi:{{ $col }}">
                    <div class="cfa-kpi-top">
                        <span class="cfa-kpi-ico">{!! $icon($k['icon']) !!}</span>
                        <span class="cfa-kpi-trend {{ $tc }}">
                            {!! $icon($tc === 'down' ? 'flag' : 'bolt') !!}
                            {{ $k['trend'] > 0 ? '+' : '' }}{{ $k['trend'] }}%
                        </span>
                    </div>
                    <div class="cfa-kpi-val">{{ $k['valeur'] }}</div>
                    <div class="cfa-kpi-label">{{ $k['label'] }}</div>
                    {!! $spark($k['spark'], $col) !!}
                    <div class="cfa-kpi-sub">{{ $k['sous'] }}</div>
                </{{ $k['url'] ? 'a' : 'div' }}>
            @endforeach
        </section>

        {{-- ============ GRAPHES ============ --}}
        <section class="cfa-ck-charts">
            {{-- Pipeline --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h">
                    <span>Pipeline commercial</span>
                    <a href="{{ \App\Filament\Resources\Candidates\CandidateResource::getUrl('index') }}" class="cfa-panel-link">Voir le pipeline →</a>
                </div>
                <div class="cfa-funnel">
                    @foreach ($pipeline['etapes'] as $i => $e)
                        <a href="{{ $e['url'] }}" class="cfa-funnel-row">
                            <span class="cfa-funnel-lbl">{{ $e['label'] }}</span>
                            <span class="cfa-funnel-bar-wrap">
                                <span class="cfa-funnel-bar" style="width:{{ max($e['taux'], 4) }}%;--i:{{ $i }}"></span>
                            </span>
                            <span class="cfa-funnel-val">{{ $e['valeur'] }}<small>{{ $e['taux'] }}%</small></span>
                        </a>
                    @endforeach
                </div>
                <div class="cfa-funnel-foot">
                    Taux de conversion global : <b>{{ $pipeline['global'] }}%</b>
                </div>
            </div>

            {{-- Évolution --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h">
                    <span>Évolution mensuelle</span>
                    <div class="cfa-period" role="group" aria-label="Période">
                        @foreach (['3' => '3 mois', '6' => '6 mois', '12' => '12 mois'] as $val => $lbl)
                            <button type="button" wire:click="definirPeriode('{{ $val }}')"
                                class="cfa-period-btn {{ $periode === $val ? 'actif' : '' }}">{{ $lbl }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="cfa-legend">
                    @foreach ($evolution['series'] as $s)
                        <span class="cfa-legend-item"><span class="cfa-dot" style="background:{{ $s['color'] }}"></span>{{ $s['label'] }}</span>
                    @endforeach
                </div>
                <div class="cfa-line-wrap">{!! $lineChart($evolution['labels'], $evolution['series']) !!}</div>
                <div class="cfa-line-x">
                    @foreach ($evolution['labels'] as $l)<span>{{ ucfirst($l) }}</span>@endforeach
                </div>
            </div>

            {{-- Répartition --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h">
                    <span>Répartition des contrats</span>
                    <a href="{{ \App\Filament\Resources\Contracts\ContractResource::getUrl('index') }}" class="cfa-panel-link">Voir le détail →</a>
                </div>
                <div class="cfa-donut-wrap">
                    <div class="cfa-donut-chart">
                        {!! $donut($distribution['segments'], $distribution['total']) !!}
                        <div class="cfa-donut-center">
                            <b>{{ $distribution['total'] }}</b><span>Total</span>
                        </div>
                    </div>
                    <ul class="cfa-donut-legend">
                        @foreach ($distribution['segments'] as $seg)
                            <li><span class="cfa-dot" style="background:{{ $seg['color'] }}"></span>
                                {{ $seg['label'] }}<b>{{ $seg['valeur'] }}</b></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        {{-- ============ PRIORITÉS + ALERTES ============ --}}
        <section class="cfa-ck-work">
            {{-- Tâches prioritaires --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h"><span>Tâches prioritaires</span></div>
                @forelse ($priorites as $p)
                    @php $col = $tones[$p['tone']] ?? $tones['info']; @endphp
                    <div class="cfa-task" style="--tk:{{ $col }}">
                        <span class="cfa-task-ico">{!! $icon($p['icon']) !!}</span>
                        <span class="cfa-task-body">
                            <span class="cfa-task-title">{{ $p['titre'] }}
                                @if($p['niveau'])<span class="cfa-task-lvl">{{ $p['niveau'] }}</span>@endif
                            </span>
                            <span class="cfa-task-detail">{{ $p['detail'] }}</span>
                        </span>
                        @if($p['url'])<a href="{{ $p['url'] }}" class="cfa-task-btn">{{ $p['action'] }}</a>@endif
                    </div>
                @empty
                    <div class="cfa-empty">{!! $icon('check-badge') !!}<span>Aucune action prioritaire. Tout est à jour ✨</span></div>
                @endforelse
            </div>

            {{-- Alertes critiques --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h"><span>Alertes critiques</span></div>
                @forelse ($alertes as $al)
                    @php $col = $tones[$al['tone']] ?? $tones['danger']; @endphp
                    <div class="cfa-alert" style="--al:{{ $col }}">
                        <span class="cfa-alert-ico">{!! $icon('exclamation') !!}</span>
                        <span class="cfa-alert-body">
                            <span class="cfa-alert-title">{{ $al['titre'] }}</span>
                            <span class="cfa-alert-detail">{{ $al['detail'] }}</span>
                        </span>
                        @if($al['age'])<span class="cfa-alert-age">{{ $al['age'] }}</span>@endif
                    </div>
                @empty
                    <div class="cfa-empty">{!! $icon('check-badge') !!}<span>Aucune alerte critique en cours.</span></div>
                @endforelse
            </div>
        </section>

        {{-- ============ AGENDA + ACTIVITÉ ============ --}}
        <section class="cfa-ck-bottom">
            {{-- Agenda --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h">
                    <span>Agenda du jour</span>
                    <a href="{{ \App\Filament\Resources\Entretiens\EntretienResource::getUrl('index') }}" class="cfa-panel-link">Voir le planning →</a>
                </div>
                @forelse ($agenda as $ev)
                    @php $col = $tones[$ev['tone']] ?? $tones['info']; @endphp
                    <a @if($ev['url']) href="{{ $ev['url'] }}" @endif class="cfa-agenda">
                        <span class="cfa-agenda-h">{{ $ev['heure'] }}</span>
                        <span class="cfa-agenda-t">{{ $ev['titre'] }}</span>
                        <span class="cfa-agenda-b" style="--ag:{{ $col }}">{{ $ev['type'] }}</span>
                    </a>
                @empty
                    <div class="cfa-empty">{!! $icon('calendar') !!}<span>Aucun rendez-vous aujourd'hui.</span></div>
                @endforelse
            </div>

            {{-- Activité récente --}}
            <div class="cfa-panel">
                <div class="cfa-panel-h"><span>Activité récente</span></div>
                @forelse ($activite as $ac)
                    @php $col = $tones[$ac['tone']] ?? $tones['info']; @endphp
                    <div class="cfa-activity">
                        <span class="cfa-activity-dot" style="background:{{ $col }}"></span>
                        <span class="cfa-activity-body">
                            <span class="cfa-activity-t">{{ $ac['titre'] }}</span>
                            <span class="cfa-activity-m">{{ $ac['quand'] }} · {{ $ac['auteur'] }}</span>
                        </span>
                    </div>
                @empty
                    <div class="cfa-empty">{!! $icon('clock') !!}<span>Aucune activité récente enregistrée.</span></div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
