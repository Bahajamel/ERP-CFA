{{-- Grille des modules principaux. Ancre #fonctionnalites. --}}
@php
    $modules = [
        [
            'titre' => 'Candidats', 'accent' => 'indigo',
            'icone' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a8.25 8.25 0 0 1 15 0',
            'desc' => 'Centralisez les informations, suivez le parcours, gérez les statuts et documents, et affectez les dossiers aux équipes.',
        ],
        [
            'titre' => 'Entreprises & besoins', 'accent' => 'violet',
            'icone' => 'M3.75 21h16.5M4.5 3h15l-.75 18h-13.5L4.5 3Zm3.75 6h7.5m-7.5 3.75h7.5',
            'desc' => 'Gérez vos entreprises partenaires, leurs contacts, leurs besoins en recrutement et l\'historique des partenariats.',
        ],
        [
            'titre' => 'Matching', 'accent' => 'sky',
            'icone' => 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5M16.5 3 21 7.5m0 0L16.5 12M21 7.5H7.5',
            'desc' => 'Rapprochez candidats et besoins, présélectionnez les profils et suivez les propositions dans un pipeline de placement.',
        ],
        [
            'titre' => 'Contrats & OPCO', 'accent' => 'emerald',
            'icone' => 'M9 12h6m-6 3h6m2.25 4.5H6.75A2.25 2.25 0 0 1 4.5 17.25V6.75A2.25 2.25 0 0 1 6.75 4.5h6.879a1.5 1.5 0 0 1 1.06.44l3.371 3.37a1.5 1.5 0 0 1 .44 1.061v7.879a2.25 2.25 0 0 1-2.25 2.25Z',
            'desc' => 'Suivez les contrats, les pièces manquantes, les dossiers OPCO, les échéances et les ruptures — avec relances automatiques.',
        ],
        [
            'titre' => 'Formation & scolarité', 'accent' => 'amber',
            'icone' => 'M4.26 10.147a60.436 60.436 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.905 59.905 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
            'desc' => 'Programmes, classes, plannings, livrets, examens et suivi pédagogique des apprenants, réunis dans un même espace.',
        ],
        [
            'titre' => 'Finance', 'accent' => 'teal',
            'icone' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'desc' => 'Recettes, dépenses, financements, facturation et pièces justificatives, avec des indicateurs financiers clairs.',
        ],
        [
            'titre' => 'Tâches & alertes', 'accent' => 'rose',
            'icone' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
            'desc' => 'Tâches assignées, rappels, échéances, notifications et priorités : plus rien ne passe à travers les mailles.',
        ],
        [
            'titre' => 'Assistant de pilotage', 'accent' => 'cyan',
            'icone' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
            'desc' => 'Indicateurs, synthèses, alertes et assistants FAQ spécialisés par module pour aider la décision au quotidien.',
        ],
    ];
    $accents = [
        'indigo' => 'bg-indigo-50 text-indigo-600', 'violet' => 'bg-violet-50 text-violet-600',
        'sky' => 'bg-sky-50 text-sky-600', 'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600', 'teal' => 'bg-teal-50 text-teal-600',
        'rose' => 'bg-rose-50 text-rose-600', 'cyan' => 'bg-cyan-50 text-cyan-600',
    ];
@endphp

<section id="fonctionnalites" class="scroll-mt-20 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Fonctionnalités</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Tous vos métiers, un seul outil</h2>
            <p class="mt-4 text-lg text-slate-600">Chaque module répond à un besoin concret des CFA, du premier contact candidat jusqu'au suivi pédagogique et financier.</p>
        </div>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" data-reveal>
            @foreach ($modules as $m)
                <div class="group rounded-2xl border border-slate-100 bg-white p-6 transition hover:-translate-y-1 hover:border-indigo-100 hover:shadow-lg hover:shadow-indigo-900/5">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $accents[$m['accent']] }}">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m['icone'] }}"/></svg>
                    </span>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $m['titre'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $m['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>