{{-- Section « Pour qui ? » — onglets par profil (JS vanilla, cf. layout). --}}
@php
    $profils = [
        'direction' => [
            'label' => 'Direction',
            'icone' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21',
            'accroche' => 'Une vision globale, en temps réel.',
            'points' => ['Tableau de bord et indicateurs clés', 'Alertes sur les dossiers à risque', 'Suivi de l\'activité de toutes les équipes', 'Aide à la décision au quotidien'],
        ],
        'commercial' => [
            'label' => 'Équipe commerciale',
            'icone' => 'M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z',
            'accroche' => 'Du premier contact au placement.',
            'points' => ['Candidats, entreprises et besoins centralisés', 'Offres d\'alternance et matching', 'Pipeline de placement clair', 'Suivi des contrats en cours'],
        ],
        'administratif' => [
            'label' => 'Équipe administrative',
            'icone' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
            'accroche' => 'Des dossiers complets, sans oubli.',
            'points' => ['Contrats, pièces et dossiers OPCO', 'Suivi des financements', 'Relances automatiques', 'Documents sécurisés et retrouvables'],
        ],
        'pedagogique' => [
            'label' => 'Équipe pédagogique',
            'icone' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
            'accroche' => 'Le suivi des apprenants, simplifié.',
            'points' => ['Formations, classes et plannings', 'Évaluations, livrets et examens', 'Suivi pédagogique des apprenants', 'Documents et ressources partagés'],
        ],
        'financiere' => [
            'label' => 'Équipe financière',
            'icone' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'accroche' => 'Une comptabilité sous contrôle.',
            'points' => ['Facturation et suivi des règlements', 'Dépenses et financements', 'Pièces justificatives centralisées', 'Reporting financier'],
        ],
    ];
@endphp

<section id="pour-qui" class="scroll-mt-20 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Pour qui ?</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Une plateforme, tous vos métiers</h2>
            <p class="mt-4 text-lg text-slate-600">Chaque équipe retrouve son espace de travail, tout en partageant les mêmes données à jour.</p>
        </div>

        <div class="mt-12" data-tabs data-reveal>
            {{-- Onglets --}}
            <div class="flex flex-wrap justify-center gap-2" role="tablist" aria-label="Profils utilisateurs">
                @foreach ($profils as $cle => $p)
                    <button type="button" data-tab="{{ $cle }}" role="tab" id="onglet-{{ $cle }}" aria-controls="panneau-{{ $cle }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        class="{{ $loop->first ? 'est-actif' : '' }} rounded-full px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50 aria-selected:bg-indigo-600 aria-selected:text-white aria-selected:ring-indigo-600">
                        {{ $p['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Panneaux --}}
            <div class="mx-auto mt-8 max-w-3xl">
                @foreach ($profils as $cle => $p)
                    <div data-panel="{{ $cle }}" id="panneau-{{ $cle }}" role="tabpanel" aria-labelledby="onglet-{{ $cle }}" @unless ($loop->first) hidden @endunless
                        class="rounded-2xl border border-slate-100 bg-slate-50 p-8">
                        <div class="flex items-start gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $p['icone'] }}"/></svg>
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">{{ $p['label'] }}</h3>
                                <p class="text-sm text-indigo-600">{{ $p['accroche'] }}</p>
                            </div>
                        </div>
                        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($p['points'] as $point)
                                <li class="flex gap-2.5 text-sm text-slate-600">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>