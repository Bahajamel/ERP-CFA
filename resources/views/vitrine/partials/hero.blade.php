{{-- Hero : proposition de valeur + aperçu produit (mockup HTML/CSS fidèle,
     pas une fausse capture). --}}
<section class="relative overflow-hidden bg-gradient-to-b from-indigo-50/70 via-white to-white">
    <div class="pointer-events-none absolute inset-x-0 -top-24 h-64 bg-gradient-to-b from-violet-100/40 to-transparent blur-3xl" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-10 lg:py-24 lg:px-8">
        {{-- Colonne texte --}}
        <div data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/15">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                ERP conçu pour les CFA et organismes de formation
            </span>

            <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
                Pilotez votre CFA depuis <span class="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">une seule plateforme</span>
            </h1>

            <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">
                Centralisez vos candidats, entreprises, contrats, formations, financements et
                obligations administratives dans un ERP pensé pour le quotidien des CFA.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="#demonstration" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Demander une démonstration
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
                <a href="#solution" class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 transition hover:bg-slate-50">
                    Découvrir la solution
                </a>
            </div>

            <p class="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                <span class="font-medium text-slate-700">Gestion centralisée</span> ·
                <span class="font-medium text-slate-700">Automatisation</span> ·
                <span class="font-medium text-slate-700">Suivi OPCO</span> ·
                <span class="font-medium text-slate-700">Pilotage en temps réel</span>
            </p>
        </div>

        {{-- Colonne visuel : mockup produit --}}
        <div data-reveal class="relative">
            <div class="pointer-events-none absolute -inset-4 rounded-3xl bg-gradient-to-tr from-indigo-200/40 to-violet-200/40 blur-2xl" aria-hidden="true"></div>
            @include('vitrine.partials.mockup-app')
        </div>
    </div>
</section>