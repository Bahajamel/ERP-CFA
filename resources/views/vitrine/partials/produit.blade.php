{{-- Blocs produit alternant texte et visuel. Chaque visuel est une maquette
     HTML/CSS légère, cohérente avec l'ERP (pas une fausse capture).

     ⚠️ Tailwind v4 ne détecte que les classes littérales : toute couleur passe
     par une map de chaînes complètes, jamais par interpolation (bg-{{ '$x' }}-100). --}}
<section class="bg-slate-50">
    <div class="mx-auto max-w-7xl space-y-20 px-4 py-20 sm:px-6 lg:px-8 lg:space-y-28">

        {{-- Bloc 1 — Centraliser --}}
        <div class="grid items-center gap-10 lg:grid-cols-2" data-reveal>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Centralisation</p>
                <h3 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Centralisez toute votre activité</h3>
                <p class="mt-4 text-slate-600">Candidats, entreprises, formations et contrats sont réunis dans une seule plateforme. Fini les fichiers dispersés : chaque information a sa place et reste accessible à la bonne équipe.</p>
                <ul class="mt-5 space-y-2.5 text-sm text-slate-600">
                    @foreach (['Un dossier unique par candidat et par entreprise', 'Documents rattachés et retrouvables en un clic', 'Historique complet des échanges et des statuts'] as $point)
                        <li class="flex gap-2.5"><svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-lg shadow-indigo-900/5 ring-1 ring-slate-900/5">
                <div class="space-y-2.5">
                    @foreach ([['Candidats', 'w-4/5', 'bg-indigo-500'], ['Entreprises', 'w-3/5', 'bg-violet-500'], ['Formations', 'w-2/3', 'bg-sky-500'], ['Contrats', 'w-1/2', 'bg-emerald-500']] as [$label, $largeur, $couleur])
                        <div>
                            <div class="mb-1 flex justify-between text-[11px] font-medium text-slate-500"><span>{{ $label }}</span></div>
                            <div class="h-2.5 w-full rounded-full bg-slate-100"><div class="{{ $largeur }} h-2.5 rounded-full {{ $couleur }}"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Bloc 2 — Automatiser (visuel à gauche en desktop) --}}
        <div class="grid items-center gap-10 lg:grid-cols-2" data-reveal>
            <div class="lg:order-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Automatisation</p>
                <h3 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Automatisez les tâches répétitives</h3>
                <p class="mt-4 text-slate-600">L'ERP prend en charge le travail à faible valeur : relances, notifications, génération de documents, suivi des pièces et alertes sur les échéances. Vos équipes se concentrent sur l'essentiel.</p>
                <ul class="mt-5 space-y-2.5 text-sm text-slate-600">
                    @foreach (['Relances et notifications automatiques', 'Génération de documents à partir des dossiers', 'Alertes sur les pièces manquantes et les échéances'] as $point)
                        <li class="flex gap-2.5"><svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="lg:order-1 rounded-2xl bg-white p-5 shadow-lg shadow-indigo-900/5 ring-1 ring-slate-900/5">
                <div class="space-y-2.5">
                    @foreach ([['Contrat signé', 'Génération de la convention', 'bg-emerald-100 text-emerald-600'], ['Pièce manquante', 'Relance envoyée à l\'entreprise', 'bg-amber-100 text-amber-600'], ['Échéance à 7 jours', 'Rappel créé pour le gestionnaire', 'bg-indigo-100 text-indigo-600']] as [$declencheur, $action, $pastille])
                        <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $pastille }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[11px] font-semibold text-slate-700">{{ $declencheur }}</p>
                                <p class="truncate text-[10px] text-slate-400">→ {{ $action }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-semibold text-emerald-600">auto</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Bloc 3 — Piloter --}}
        <div class="grid items-center gap-10 lg:grid-cols-2" data-reveal>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Pilotage</p>
                <h3 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Pilotez grâce aux indicateurs</h3>
                <p class="mt-4 text-slate-600">La direction dispose d'une vision en temps réel : candidats, contrats signés, dossiers en attente, financements et alertes. Les décisions s'appuient sur des chiffres à jour, pas sur des ressentis.</p>
                <ul class="mt-5 space-y-2.5 text-sm text-slate-600">
                    @foreach (['Tableau de bord clair pour la direction', 'Suivi des performances commerciales et pédagogiques', 'Alertes sur les dossiers à risque'] as $point)
                        <li class="flex gap-2.5"><svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-lg shadow-indigo-900/5 ring-1 ring-slate-900/5">
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach ([['Candidats', '128', 'bg-indigo-50 text-indigo-600'], ['Contrats signés', '54', 'bg-emerald-50 text-emerald-600'], ['En attente', '9', 'bg-amber-50 text-amber-600'], ['Financements', '312 k€', 'bg-violet-50 text-violet-600']] as [$label, $valeur, $classes])
                        <div class="rounded-lg {{ $classes }} p-3">
                            <p class="text-[11px] font-medium text-slate-500">{{ $label }}</p>
                            <p class="mt-0.5 text-xl font-bold">{{ $valeur }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2.5 flex items-end gap-1.5 rounded-lg bg-slate-50 p-3" style="height:72px">
                    @foreach (['h-6','h-10','h-8','h-14','h-11','h-16','h-12'] as $h)
                        <div class="{{ $h }} flex-1 rounded-t bg-gradient-to-t from-indigo-500 to-violet-400"></div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Bloc 4 — Adapter (avantage concurrentiel) --}}
        <div class="grid items-center gap-10 lg:grid-cols-2" data-reveal>
            <div class="lg:order-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Flexibilité</p>
                <h3 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Adaptez la plateforme à votre organisation</h3>
                <p class="mt-4 text-slate-600">Chaque CFA travaille à sa manière. Créez des tableaux personnalisés, ajoutez des colonnes, ajustez les statuts et configurez les vues pour coller à vos processus — sans développement.</p>
                <ul class="mt-5 space-y-2.5 text-sm text-slate-600">
                    @foreach (['Tableaux et colonnes personnalisables', 'Statuts et vues configurables', 'La plateforme s\'adapte à vos méthodes, pas l\'inverse'] as $point)
                        <li class="flex gap-2.5"><svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="lg:order-1 rounded-2xl bg-white p-4 shadow-lg shadow-indigo-900/5 ring-1 ring-slate-900/5">
                <div class="overflow-hidden rounded-lg ring-1 ring-slate-100">
                    <div class="grid grid-cols-4 gap-px bg-slate-100 text-[10px] font-semibold text-slate-500">
                        @foreach (['Candidat', 'Statut', 'Priorité', 'Suivi'] as $col)
                            <div class="bg-slate-50 px-2 py-1.5">{{ $col }}</div>
                        @endforeach
                    </div>
                    @foreach ([['L. Martin', 'Entretien', 'Haute', 'bg-amber-100 text-amber-700'], ['K. Benali', 'Retenu', 'Moyenne', 'bg-emerald-100 text-emerald-700'], ['S. Durand', 'À qualifier', 'Basse', 'bg-slate-100 text-slate-700']] as [$nom, $statut, $prio, $badge])
                        <div class="grid grid-cols-4 gap-px bg-slate-100 text-[10px]">
                            <div class="bg-white px-2 py-1.5 font-medium text-slate-700">{{ $nom }}</div>
                            <div class="bg-white px-2 py-1.5"><span class="rounded-full {{ $badge }} px-1.5 py-0.5">{{ $statut }}</span></div>
                            <div class="bg-white px-2 py-1.5 text-slate-500">{{ $prio }}</div>
                            <div class="bg-white px-2 py-1.5 text-slate-400">●●○</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex items-center gap-1 text-[10px] font-medium text-indigo-600">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Ajouter une colonne
                </div>
            </div>
        </div>

    </div>
</section>