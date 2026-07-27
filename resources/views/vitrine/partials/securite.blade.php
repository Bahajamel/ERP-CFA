{{-- Section sécurité. UNIQUEMENT des éléments réellement présents dans l'ERP.
     Aucun badge de certification (ISO, RGPD, Qualiopi, HDS) : non obtenus. --}}
@php
    $mesures = [
        ['M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'Rôles et permissions', 'Chaque utilisateur n\'accède qu\'aux données et actions autorisées par son rôle.'],
        ['M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z', 'Authentification multifacteur', 'La double authentification protège les comptes sensibles comme les administrateurs.'],
        ['M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z', 'Documents privés', 'Les pièces sensibles sont stockées sur un espace privé, jamais exposées publiquement.'],
        ['M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244', 'Liens sécurisés et temporaires', 'L\'accès aux documents passe par des liens signés à durée de vie limitée.'],
        ['M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25', 'Historique des actions', 'Les opérations sensibles sont tracées, ce qui facilite le suivi et les contrôles.'],
        ['M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z', 'Protection des données', 'La solution est conçue selon des bonnes pratiques de sécurité et de protection des données.'],
    ];
@endphp

<section id="securite" class="scroll-mt-20 bg-slate-900">
    <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-400">Sécurité</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Vos données sont protégées à chaque étape</h2>
            <p class="mt-4 text-lg text-slate-300">La gestion d'un CFA implique des données personnelles sensibles. Meridian CFA les protège par des mécanismes intégrés à la plateforme.</p>
        </div>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-reveal>
            @foreach ($mesures as [$icone, $titre, $desc])
                <div class="rounded-2xl border border-slate-800 bg-slate-800/40 p-6">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-300">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}"/></svg>
                    </span>
                    <h3 class="mt-4 text-base font-semibold text-white">{{ $titre }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $desc }}</p>
                </div>
            @endforeach
        </div>

        <p class="mx-auto mt-8 max-w-2xl text-center text-xs text-slate-500" data-reveal>
            Meridian CFA applique des bonnes pratiques de sécurité et de protection des données.
            La solution ne revendique aucune certification officielle tant que celle-ci n'a pas été obtenue.
        </p>
    </div>
</section>