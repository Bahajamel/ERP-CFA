{{-- Aperçu produit : maquette HTML/CSS fidèle au COCKPIT réel de l'ERP —
     sidebar bleu nuit à groupes (comme Filament), onglets par service, tuiles
     KPI avec mini-courbes et panneau « À retenir ». Couleurs alignées sur le
     thème réel (--cfa-sidebar #10162b, --cfa-accent #4f46e5, cyan #06b6d4).
     Ce n'est pas une capture mais une représentation cohérente avec le produit. --}}
@php
    // Tuiles KPI reprises du cockpit réel (CockpitData) : libellé, valeur,
    // sous-titre, teinte sémantique, points de la mini-courbe.
    $kpis = [
        ['Entretiens à planifier', '12', 'Candidats sans créneau', '#3b82f6', [4,6,5,8,7,9,12]],
        ['Contrats à faire signer', '8',  'Signature incomplète',   '#6366f1', [2,3,3,5,4,6,8]],
        ['Admissions à valider',    '5',  'Dossiers à vérifier',     '#14b8a6', [1,2,2,3,4,4,5]],
        ['Dossiers OPCO en cours',  '17', 'Financement à sécuriser', '#f59e0b', [9,11,10,13,14,15,17]],
    ];

    // Génère le tracé d'une mini-courbe (aire + ligne) à partir des points.
    $spark = function (array $s, string $color): string {
        $w = 120; $h = 34; $p = 2;
        $min = min($s); $max = max($s); $range = ($max - $min) ?: 1;
        $n = count($s); $step = ($w - 2 * $p) / max($n - 1, 1);
        $pts = [];
        foreach ($s as $i => $v) {
            $x = $p + $i * $step;
            $y = $h - $p - (($v - $min) / $range) * ($h - 2 * $p);
            $pts[] = round($x, 1).','.round($y, 1);
        }
        $line = 'M '.implode(' L ', $pts);
        $id = 'v'.substr(md5($color.$line), 0, 6);
        $area = $line.' L '.round($p + ($n - 1) * $step, 1).','.($h - $p).' L '.$p.','.($h - $p).' Z';
        return "<svg viewBox='0 0 $w $h' preserveAspectRatio='none' class='h-full w-full'>"
            ."<defs><linearGradient id='$id' x1='0' y1='0' x2='0' y2='1'>"
            ."<stop offset='0' stop-color='$color' stop-opacity='0.25'/>"
            ."<stop offset='1' stop-color='$color' stop-opacity='0'/></linearGradient></defs>"
            ."<path d='$area' fill='url(#$id)'/>"
            ."<path d='$line' fill='none' stroke='$color' stroke-width='2'/></svg>";
    };
@endphp

<div class="relative overflow-hidden rounded-2xl bg-white shadow-2xl shadow-indigo-900/10 ring-1 ring-slate-900/10">
    {{-- Barre de navigateur --}}
    <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
        <span class="h-2.5 w-2.5 rounded-full bg-rose-300"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
        <span class="ml-3 flex-1 truncate rounded-md bg-white px-3 py-1 text-[11px] text-slate-400 ring-1 ring-slate-200">app.meridian-cfa.fr/admin</span>
    </div>

    <div class="flex">
        {{-- Sidebar bleu nuit, à groupes (comme le vrai panneau Filament) --}}
        <div class="hidden w-44 shrink-0 bg-[#10162b] p-3 sm:block">
            <div class="flex items-center gap-2 px-1 py-1.5">
                <span class="h-6 w-6 rounded-md bg-gradient-to-br from-indigo-500 to-cyan-400"></span>
                <span class="text-[11px] font-bold text-white">Meridian CFA</span>
            </div>

            <div class="mt-4 space-y-3">
                @php
                    $groupes = [
                        'Pilotage'         => [['Tableau de bord', true]],
                        'Commercial'       => [['Candidats', false], ['Entreprises', false], ['Offres', false]],
                        'Contrats & OPCO'  => [['Contrats', false], ['Dossiers OPCO', false]],
                        'Finance'          => [['Facturation', false]],
                    ];
                @endphp
                @foreach ($groupes as $groupe => $items)
                    <div>
                        <p class="px-2 pb-1 text-[8.5px] font-semibold uppercase tracking-wider text-[rgba(148,163,199,0.65)]">{{ $groupe }}</p>
                        <div class="space-y-0.5">
                            @foreach ($items as [$item, $actif])
                                <div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[11px] {{ $actif ? 'bg-indigo-600 font-semibold text-white shadow-sm shadow-indigo-900/40' : 'text-[rgba(219,228,255,0.72)]' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $actif ? 'bg-white' : 'bg-[rgba(148,163,214,0.5)]' }}"></span>
                                    {{ $item }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Contenu : le cockpit --}}
        <div class="min-w-0 flex-1 bg-[#f3f5fa] p-4">
            {{-- En-tête cockpit + onglets par service --}}
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[12px] font-bold text-slate-800">Cockpit</p>
                    <p class="text-[9.5px] text-slate-400">Vue globale · aujourd'hui</p>
                </div>
                <span class="rounded-md bg-white px-2 py-1 text-[10px] text-slate-400 ring-1 ring-slate-200">Rechercher…</span>
            </div>
            <div class="mt-2.5 flex flex-wrap gap-1.5">
                @foreach (['Vue globale' => true, 'Commercial' => false, 'Admissions' => false, 'Contrats' => false, 'Finance' => false] as $onglet => $actif)
                    <span class="rounded-full px-2.5 py-1 text-[9.5px] font-medium {{ $actif ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 ring-1 ring-slate-200' }}">{{ $onglet }}</span>
                @endforeach
            </div>

            {{-- Tuiles KPI avec mini-courbe (cockpit réel) --}}
            <div class="mt-3 grid grid-cols-2 gap-2.5">
                @foreach ($kpis as [$label, $valeur, $sous, $tone, $serie])
                    <div class="overflow-hidden rounded-xl bg-white p-2.5 shadow-sm ring-1 ring-slate-100">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-[9.5px] font-medium text-slate-500">{{ $label }}</p>
                                <p class="mt-0.5 text-xl font-extrabold leading-none" style="color: {{ $tone }}">{{ $valeur }}</p>
                            </div>
                            <span class="h-6 w-6 shrink-0 rounded-lg" style="background: {{ $tone }}1a"></span>
                        </div>
                        <div class="mt-1 h-6">{!! $spark($serie, $tone) !!}</div>
                        <p class="mt-0.5 truncate text-[8.5px] text-slate-400">{{ $sous }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Panneau « À retenir » (synthèse actionnable du cockpit) --}}
            <div class="mt-2.5 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="flex items-center gap-1.5 border-b border-slate-100 px-3 py-2">
                    <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                    <span class="text-[11px] font-semibold text-slate-700">À retenir</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ([['#f43f5e', '2 dossiers OPCO à relancer avant échéance'], ['#f59e0b', '3 contrats en attente de signature employeur'], ['#14b8a6', '5 admissions prêtes à valider']] as [$c, $texte])
                        <div class="flex items-center gap-2.5 px-3 py-1.5">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $c }}"></span>
                            <p class="truncate text-[10px] text-slate-600">{{ $texte }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Carte flottante « alerte » pour l'effet produit --}}
<div class="absolute -bottom-4 -left-3 hidden rounded-xl bg-white p-3 shadow-xl ring-1 ring-slate-900/5 sm:block">
    <div class="flex items-center gap-2.5">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
        </span>
        <div>
            <p class="text-[11px] font-semibold text-slate-700">3 pièces OPCO manquantes</p>
            <p class="text-[10px] text-slate-400">Relance automatique programmée</p>
        </div>
    </div>
</div>
