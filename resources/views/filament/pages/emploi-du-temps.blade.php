<x-filament-panels::page>
    <style>
        .edt-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .edt-toolbar select {
            min-width: 18rem; padding: .5rem .75rem; border-radius: .5rem; font-size: .875rem;
            border: 1px solid rgb(209 213 219); background: #fff; color: rgb(17 24 39);
        }
        .dark .edt-toolbar select { border-color: rgb(55 65 81); background: rgb(17 24 39); color: #fff; }
        .edt-nav { display: flex; align-items: center; gap: .25rem; margin-left: auto; }
        .edt-btn {
            padding: .45rem .8rem; border-radius: .5rem; font-size: .8rem; font-weight: 600; cursor: pointer;
            border: 1px solid rgb(209 213 219); background: #fff; color: rgb(55 65 81);
        }
        .edt-btn:hover { background: rgb(243 244 246); }
        .dark .edt-btn { border-color: rgb(55 65 81); background: rgb(31 41 55); color: rgb(209 213 219); }
        .dark .edt-btn:hover { background: rgb(55 65 81); }
        .edt-periode { font-size: .875rem; font-weight: 600; color: rgb(55 65 81); }
        .dark .edt-periode { color: rgb(209 213 219); }

        .edt-grille {
            display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .5rem; margin-top: 1rem;
        }
        @media (max-width: 1024px) { .edt-grille { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px)  { .edt-grille { grid-template-columns: 1fr; } }

        .edt-jour {
            border: 1px solid rgb(229 231 235); border-radius: .75rem; background: #fff;
            display: flex; flex-direction: column; min-height: 12rem; overflow: hidden;
        }
        .dark .edt-jour { border-color: rgb(55 65 81); background: rgb(17 24 39); }
        .edt-jour-titre {
            padding: .5rem .75rem; font-size: .8rem; font-weight: 700; text-transform: capitalize;
            color: rgb(55 65 81); border-bottom: 1px solid rgb(229 231 235); display: flex; justify-content: space-between; align-items: center;
        }
        .dark .edt-jour-titre { color: rgb(209 213 219); border-color: rgb(55 65 81); }
        .edt-jour--today .edt-jour-titre { background: rgb(238 242 255); color: rgb(67 56 202); }
        .dark .edt-jour--today .edt-jour-titre { background: rgb(49 46 129 / .35); color: rgb(165 180 252); }
        .edt-ajouter { font-size: 1rem; font-weight: 700; text-decoration: none; color: rgb(99 102 241); line-height: 1; }
        .edt-ajouter:hover { color: rgb(67 56 202); }

        .edt-seances { padding: .5rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
        .edt-vide { font-size: .75rem; color: rgb(156 163 175); text-align: center; margin: auto; }

        .edt-carte {
            display: block; width: 100%; text-align: left; font: inherit; cursor: pointer;
            text-decoration: none; border-radius: .5rem; padding: .5rem .6rem .5rem .75rem;
            background: rgb(249 250 251); border: 1px solid rgb(229 231 235); border-left: 4px solid var(--edt-couleur, #6366f1);
            transition: box-shadow .15s ease;
        }
        .edt-carte:hover { box-shadow: 0 2px 8px rgb(0 0 0 / .12); }
        .edt-carte:focus-visible { outline: 2px solid rgb(99 102 241); outline-offset: 1px; }
        .dark .edt-carte { background: rgb(31 41 55); border-color: rgb(55 65 81); }
        .edt-carte--annulee { opacity: .55; }
        .edt-carte--annulee .edt-matiere { text-decoration: line-through; }
        .edt-heures { font-size: .7rem; font-weight: 700; color: rgb(107 114 128); }
        .dark .edt-heures { color: rgb(156 163 175); }
        .edt-matiere { font-size: .8rem; font-weight: 700; color: rgb(17 24 39); margin-top: .1rem; }
        .dark .edt-matiere { color: #fff; }
        .edt-detail { font-size: .7rem; color: rgb(107 114 128); margin-top: .1rem; }
        .dark .edt-detail { color: rgb(156 163 175); }
        .edt-statut {
            display: inline-block; margin-top: .35rem; padding: .1rem .5rem; border-radius: 999px;
            font-size: .65rem; font-weight: 700;
        }
        .edt-statut--planifiee { background: rgb(224 231 255); color: rgb(67 56 202); }
        .edt-statut--validee  { background: rgb(209 250 229); color: rgb(4 120 87); }
        .edt-statut--annulee  { background: rgb(254 226 226); color: rgb(185 28 28); }
        .dark .edt-statut--planifiee { background: rgb(49 46 129 / .5); color: rgb(165 180 252); }
        .dark .edt-statut--validee  { background: rgb(6 78 59 / .5);  color: rgb(110 231 183); }
        .dark .edt-statut--annulee  { background: rgb(127 29 29 / .5); color: rgb(252 165 165); }
    </style>

    {{-- Barre d'outils : formation + navigation de semaine --}}
    <div class="edt-toolbar">
        <select wire:model.live="promotionId" aria-label="Classe">
            @foreach ($classes as $id => $libelle)
                <option value="{{ $id }}">{{ $libelle }}</option>
            @endforeach
        </select>

        <div class="edt-nav">
            <span class="edt-periode">
                Semaine du {{ $lundi->translatedFormat('j F') }} au {{ $samedi->translatedFormat('j F Y') }}
            </span>
            <button type="button" class="edt-btn" wire:click="semainePrecedente" title="Semaine précédente">‹</button>
            <button type="button" class="edt-btn" wire:click="semaineCourante">Aujourd'hui</button>
            <button type="button" class="edt-btn" wire:click="semaineSuivante" title="Semaine suivante">›</button>
        </div>
    </div>

    {{-- Grille lundi → samedi --}}
    <div class="edt-grille">
        @foreach ($jours as $jour)
            @php $seancesDuJour = $seancesParJour->get($jour->toDateString(), collect()); @endphp
            <div class="edt-jour {{ $jour->isToday() ? 'edt-jour--today' : '' }}">
                <div class="edt-jour-titre">
                    <span>{{ $jour->translatedFormat('l j/m') }}</span>
                    <a class="edt-ajouter"
                       href="{{ $this->lienCreation($jour->toDateString()) }}"
                       title="Ajouter une séance le {{ $jour->translatedFormat('j F') }}">+</a>
                </div>
                <div class="edt-seances">
                    @forelse ($seancesDuJour as $seance)
                        <button type="button"
                           class="edt-carte {{ $seance->statut === \App\Enums\SeanceStatut::Annulee ? 'edt-carte--annulee' : '' }}"
                           style="--edt-couleur: {{ $couleurs[$seance->libelle] ?? '#6366f1' }}"
                           wire:click="mountAction('voirSeance', { seance: {{ $seance->id }} })">
                            <div class="edt-heures">
                                {{ substr($seance->heure_debut ?? '', 0, 5) ?: '—' }}
                                – {{ substr($seance->heure_fin ?? '', 0, 5) ?: '—' }}
                            </div>
                            <div class="edt-matiere">
                                {{ $seance->libelle ?? 'Séance' }}
                            </div>
                            <div class="edt-detail">
                                {{ $seance->promotion?->libelle }}
                                @if ($seance->formateur) · {{ $seance->formateur->name }} @endif
                            </div>
                            <span class="edt-statut edt-statut--{{ $seance->statut->value }}">
                                {{ $seance->statut->getLabel() }}
                            </span>
                        </button>
                    @empty
                        <span class="edt-vide">Aucune séance</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
