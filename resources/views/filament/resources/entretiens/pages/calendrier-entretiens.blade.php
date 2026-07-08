<x-filament-panels::page>
    <style>
        .ent-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .ent-nav { display: flex; align-items: center; gap: .25rem; margin-left: auto; }
        .ent-btn {
            padding: .45rem .8rem; border-radius: .5rem; font-size: .8rem; font-weight: 600; cursor: pointer;
            border: 1px solid rgb(209 213 219); background: #fff; color: rgb(55 65 81);
        }
        .ent-btn:hover { background: rgb(243 244 246); }
        .dark .ent-btn { border-color: rgb(55 65 81); background: rgb(31 41 55); color: rgb(209 213 219); }
        .dark .ent-btn:hover { background: rgb(55 65 81); }
        .ent-periode { font-size: .875rem; font-weight: 600; color: rgb(55 65 81); }
        .dark .ent-periode { color: rgb(209 213 219); }

        .ent-grille {
            display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .5rem; margin-top: 1rem;
        }
        @media (max-width: 1024px) { .ent-grille { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px)  { .ent-grille { grid-template-columns: 1fr; } }

        .ent-jour {
            border: 1px solid rgb(229 231 235); border-radius: .75rem; background: #fff;
            display: flex; flex-direction: column; min-height: 12rem; overflow: hidden;
        }
        .dark .ent-jour { border-color: rgb(55 65 81); background: rgb(17 24 39); }
        .ent-jour-titre {
            padding: .5rem .75rem; font-size: .8rem; font-weight: 700; text-transform: capitalize;
            color: rgb(55 65 81); border-bottom: 1px solid rgb(229 231 235); display: flex; justify-content: space-between; align-items: center;
        }
        .dark .ent-jour-titre { color: rgb(209 213 219); border-color: rgb(55 65 81); }
        .ent-jour--today .ent-jour-titre { background: rgb(238 242 255); color: rgb(67 56 202); }
        .dark .ent-jour--today .ent-jour-titre { background: rgb(49 46 129 / .35); color: rgb(165 180 252); }
        .ent-ajouter { font-size: 1rem; font-weight: 700; text-decoration: none; color: rgb(99 102 241); line-height: 1; }
        .ent-ajouter:hover { color: rgb(67 56 202); }

        .ent-cartes { padding: .5rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
        .ent-vide { font-size: .75rem; color: rgb(156 163 175); text-align: center; margin: auto; }

        .ent-carte {
            display: block; text-decoration: none; border-radius: .5rem; padding: .5rem .6rem .5rem .75rem;
            background: rgb(249 250 251); border: 1px solid rgb(229 231 235); border-left: 4px solid var(--ent-couleur, #6366f1);
            transition: box-shadow .15s ease;
        }
        .ent-carte:hover { box-shadow: 0 2px 8px rgb(0 0 0 / .12); }
        .dark .ent-carte { background: rgb(31 41 55); border-color: rgb(55 65 81); }
        .ent-carte--terminee { opacity: .6; }
        .ent-heures { font-size: .7rem; font-weight: 700; color: rgb(107 114 128); }
        .dark .ent-heures { color: rgb(156 163 175); }
        .ent-candidat { font-size: .8rem; font-weight: 700; color: rgb(17 24 39); margin-top: .1rem; }
        .dark .ent-candidat { color: #fff; }
        .ent-detail { font-size: .7rem; color: rgb(107 114 128); margin-top: .1rem; }
        .dark .ent-detail { color: rgb(156 163 175); }
        .ent-statut {
            display: inline-block; margin-top: .35rem; padding: .1rem .5rem; border-radius: 999px;
            font-size: .65rem; font-weight: 700;
        }
        .ent-statut--planifie { background: rgb(224 231 255); color: rgb(67 56 202); }
        .ent-statut--realise  { background: rgb(209 250 229); color: rgb(4 120 87); }
        .ent-statut--annule   { background: rgb(254 226 226); color: rgb(185 28 28); }
        .ent-statut--absent, .ent-statut--a_reprogrammer, .ent-statut--a_planifier { background: rgb(254 243 199); color: rgb(180 83 9); }
        .dark .ent-statut--planifie { background: rgb(49 46 129 / .5); color: rgb(165 180 252); }
        .dark .ent-statut--realise  { background: rgb(6 78 59 / .5);  color: rgb(110 231 183); }
        .dark .ent-statut--annule   { background: rgb(127 29 29 / .5); color: rgb(252 165 165); }
        .dark .ent-statut--absent, .dark .ent-statut--a_reprogrammer, .dark .ent-statut--a_planifier { background: rgb(120 53 15 / .5); color: rgb(252 211 77); }
    </style>

    {{-- Navigation de semaine --}}
    <div class="ent-toolbar">
        <div class="ent-nav">
            <span class="ent-periode">
                Semaine du {{ $lundi->translatedFormat('j F') }} au {{ $samedi->translatedFormat('j F Y') }}
            </span>
            <button type="button" class="ent-btn" wire:click="semainePrecedente" title="Semaine précédente">‹</button>
            <button type="button" class="ent-btn" wire:click="semaineCourante">Aujourd'hui</button>
            <button type="button" class="ent-btn" wire:click="semaineSuivante" title="Semaine suivante">›</button>
        </div>
    </div>

    {{-- Grille lundi → samedi --}}
    <div class="ent-grille">
        @foreach ($jours as $jour)
            @php $entretiensDuJour = $entretiensParJour->get($jour->toDateString(), collect()); @endphp
            <div class="ent-jour {{ $jour->isToday() ? 'ent-jour--today' : '' }}">
                <div class="ent-jour-titre">
                    <span>{{ $jour->translatedFormat('l j/m') }}</span>
                    <a class="ent-ajouter"
                       href="{{ \App\Filament\Resources\Entretiens\EntretienResource::getUrl('create') }}"
                       title="Planifier un entretien">+</a>
                </div>
                <div class="ent-cartes">
                    @forelse ($entretiensDuJour as $entretien)
                        <a class="ent-carte {{ in_array($entretien->statut, [\App\Enums\EntretienStatut::Realise, \App\Enums\EntretienStatut::Annule], true) ? 'ent-carte--terminee' : '' }}"
                           style="--ent-couleur: {{ match ($entretien->statut) {
                               \App\Enums\EntretienStatut::Planifie => '#6366f1',
                               \App\Enums\EntretienStatut::Realise => '#10b981',
                               \App\Enums\EntretienStatut::Annule => '#ef4444',
                               default => '#f59e0b',
                           } }}"
                           href="{{ \App\Filament\Resources\Entretiens\EntretienResource::getUrl('edit', ['record' => $entretien]) }}">
                            <div class="ent-heures">
                                {{ substr((string) $entretien->heure_debut, 0, 5) ?: '—' }}
                                – {{ substr((string) $entretien->heure_fin, 0, 5) ?: '—' }}
                                · {{ $entretien->mode?->getLabel() }}
                            </div>
                            <div class="ent-candidat">{{ $entretien->candidate?->nom_complet ?? 'Candidat supprimé' }}</div>
                            <div class="ent-detail">
                                {{ $entretien->candidate?->formationVisee?->libelle ?? 'Formation non renseignée' }}
                                @if ($entretien->responsable) · {{ $entretien->responsable->name }} @endif
                            </div>
                            <span class="ent-statut ent-statut--{{ $entretien->statut->value }}">
                                {{ $entretien->statut->getLabel() }}
                            </span>
                        </a>
                    @empty
                        <span class="ent-vide">Aucun entretien</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
