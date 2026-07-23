{{-- Aperçu produit : maquette HTML/CSS fidèle à l'ERP (tuiles de pilotage +
     table candidats + pipeline), dans un cadre navigateur. Ce n'est pas une
     capture réelle mais une représentation cohérente avec le produit. --}}
<div class="relative overflow-hidden rounded-2xl bg-white shadow-2xl shadow-indigo-900/10 ring-1 ring-slate-900/10">
    {{-- Barre de navigateur --}}
    <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
        <span class="h-2.5 w-2.5 rounded-full bg-rose-300"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
        <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
        <span class="ml-3 flex-1 truncate rounded-md bg-white px-3 py-1 text-[11px] text-slate-400 ring-1 ring-slate-200">app.meridian-cfa.fr/admin</span>
    </div>

    {{-- Corps de l'app --}}
    <div class="flex">
        {{-- Rail latéral --}}
        <div class="hidden w-40 shrink-0 border-r border-slate-100 bg-slate-50/70 p-3 sm:block">
            <div class="flex items-center gap-2 px-2 py-1.5">
                <span class="h-5 w-5 rounded-md bg-gradient-to-br from-indigo-600 to-violet-600"></span>
                <span class="text-[11px] font-bold text-slate-700">Meridian CFA</span>
            </div>
            <div class="mt-3 space-y-1">
                @foreach (['Tableau de bord' => true, 'Candidats' => false, 'Entreprises' => false, 'Offres' => false, 'Contrats' => false, 'OPCO' => false, 'Finance' => false] as $item => $actif)
                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[11px] {{ $actif ? 'bg-indigo-600 font-semibold text-white' : 'text-slate-500' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $actif ? 'bg-white' : 'bg-slate-300' }}"></span>
                        {{ $item }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Contenu --}}
        <div class="min-w-0 flex-1 p-4">
            {{-- Tuiles de pilotage --}}
            <div class="grid grid-cols-3 gap-2.5">
                @foreach ([['Candidats actifs', '128', 'text-indigo-600', 'bg-indigo-50'], ['Contrats signés', '54', 'text-emerald-600', 'bg-emerald-50'], ['Dossiers OPCO', '17', 'text-amber-600', 'bg-amber-50']] as [$label, $valeur, $couleur, $fond])
                    <div class="rounded-lg {{ $fond }} p-2.5">
                        <p class="text-[10px] font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-0.5 text-lg font-bold {{ $couleur }}">{{ $valeur }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Table candidats --}}
            <div class="mt-3 overflow-hidden rounded-lg ring-1 ring-slate-100">
                <div class="flex items-center justify-between bg-slate-50 px-3 py-2">
                    <span class="text-[11px] font-semibold text-slate-700">Candidats récents</span>
                    <span class="rounded-md bg-white px-2 py-0.5 text-[10px] text-slate-400 ring-1 ring-slate-200">Rechercher…</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ([['LM', 'Léa Martin', 'Développeur web', 'Entretien', 'bg-amber-100 text-amber-700'], ['KB', 'Karim Benali', 'Assistant comptable', 'Retenu', 'bg-emerald-100 text-emerald-700'], ['SD', 'Sophie Durand', 'Vendeur conseil', 'À qualifier', 'bg-slate-100 text-slate-600']] as [$ini, $nom, $poste, $statut, $badge])
                        <div class="flex items-center gap-2.5 px-3 py-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-bold text-indigo-700">{{ $ini }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[11px] font-semibold text-slate-700">{{ $nom }}</p>
                                <p class="truncate text-[10px] text-slate-400">{{ $poste }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold {{ $badge }}">{{ $statut }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Mini-pipeline --}}
            <div class="mt-3 grid grid-cols-4 gap-1.5">
                @foreach ([['Créé', 'h-8'], ['Qualifié', 'h-12'], ['Proposé', 'h-16'], ['Placé', 'h-10']] as [$etape, $h])
                    <div class="rounded-md bg-slate-50 p-1.5 text-center">
                        <div class="flex items-end justify-center" style="height:44px">
                            <div class="{{ $h }} w-full rounded bg-gradient-to-t from-indigo-500 to-violet-400"></div>
                        </div>
                        <p class="mt-1 text-[9px] text-slate-400">{{ $etape }}</p>
                    </div>
                @endforeach
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