{{-- Section problème → solution. Ancre #solution (début de la présentation). --}}
<section id="solution" class="scroll-mt-20 bg-slate-50">
    <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center" data-reveal>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">La solution</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                La gestion d'un CFA ne devrait pas être dispersée entre plusieurs outils
            </h2>
            <p class="mt-4 text-lg text-slate-600">
                Fichiers Excel éparpillés, documents introuvables, relances manuelles, échéances
                oubliées : la charge administrative pèse sur toutes les équipes.
            </p>
        </div>

        <div class="mt-14 grid gap-8 lg:grid-cols-2" data-reveal>
            {{-- Avant --}}
            <div class="rounded-2xl bg-white p-7 ring-1 ring-slate-900/5">
                <h3 class="flex items-center gap-2 text-base font-semibold text-slate-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </span>
                    Sans outil dédié
                </h3>
                <ul class="mt-5 space-y-3 text-sm text-slate-600">
                    @foreach ([
                        'Informations réparties dans plusieurs fichiers Excel',
                        'Documents difficiles à retrouver',
                        'Relances effectuées à la main',
                        'Manque de visibilité sur les contrats',
                        'Erreurs dans les dossiers OPCO',
                        'Échéances difficiles à anticiper',
                        'Aucune vision globale pour la direction',
                    ] as $probleme)
                        <li class="flex gap-2.5">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            {{ $probleme }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Avec Meridian --}}
            <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-7 text-white shadow-lg shadow-indigo-600/20">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white/15">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </span>
                    Avec Meridian CFA
                </h3>
                <p class="mt-5 text-sm leading-relaxed text-indigo-100">
                    Meridian CFA rassemble vos équipes commerciales, administratives, pédagogiques
                    et financières autour d'une seule plateforme.
                </p>
                <ul class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        'Toutes les données au même endroit',
                        'Documents centralisés et sécurisés',
                        'Relances et alertes automatiques',
                        'Suivi des contrats et des dossiers OPCO',
                        'Échéances anticipées, rien n\'est oublié',
                        'Pilotage en temps réel pour la direction',
                    ] as $benefice)
                        <li class="flex gap-2.5">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            <span class="text-indigo-50">{{ $benefice }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>