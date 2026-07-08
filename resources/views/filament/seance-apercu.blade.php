<div>
    <style>
        .sa-bandeau {
            border-radius: .75rem; padding: .9rem 1rem; margin-bottom: 1rem;
            background: rgb(238 242 255); border: 1px solid rgb(199 210 254);
            border-left: 5px solid rgb(99 102 241);
        }
        .dark .sa-bandeau { background: rgb(49 46 129 / .30); border-color: rgb(67 56 202); border-left-color: rgb(129 140 248); }
        .sa-matiere { font-size: 1.15rem; font-weight: 800; color: rgb(30 27 75); }
        .dark .sa-matiere { color: #fff; }
        .sa-classe { font-size: .85rem; color: rgb(67 56 202); margin-top: .1rem; font-weight: 600; }
        .dark .sa-classe { color: rgb(165 180 252); }

        .sa-grille { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem 1rem; }
        @media (max-width: 640px) { .sa-grille { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .sa-champ dt {
            font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            color: rgb(100 116 139);
        }
        .dark .sa-champ dt { color: rgb(148 163 184); }
        .sa-champ dd { font-size: .9rem; color: rgb(15 23 42); margin-top: .15rem; font-weight: 500; }
        .dark .sa-champ dd { color: rgb(226 232 240); }

        .sa-badge { display: inline-block; padding: .15rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 700; }
        .sa-badge--planifiee { background: rgb(224 231 255); color: rgb(67 56 202); }
        .sa-badge--validee   { background: rgb(209 250 229); color: rgb(4 120 87); }
        .sa-badge--annulee   { background: rgb(254 226 226); color: rgb(185 28 28); }
        .dark .sa-badge--planifiee { background: rgb(49 46 129 / .5); color: rgb(165 180 252); }
        .dark .sa-badge--validee   { background: rgb(6 78 59 / .5);  color: rgb(110 231 183); }
        .dark .sa-badge--annulee   { background: rgb(127 29 29 / .5); color: rgb(252 165 165); }

        .sa-taux { font-weight: 800; }
        .sa-taux--ok { color: rgb(5 150 105); } .sa-taux--alerte { color: rgb(217 119 6); } .sa-taux--critique { color: rgb(220 38 38); }
    </style>

    <div class="sa-bandeau">
        <div class="sa-matiere">{{ $seance->libelle ?? 'Séance' }}</div>
        <div class="sa-classe">{{ $seance->promotion?->nom_complet ?? 'Classe non renseignée' }}</div>
    </div>

    <dl class="sa-grille">
        <div class="sa-champ">
            <dt>Date</dt>
            <dd>{{ $seance->date->translatedFormat('l j F Y') }}</dd>
        </div>
        <div class="sa-champ">
            <dt>Horaires</dt>
            <dd>
                @if ($seance->heure_debut && $seance->heure_fin)
                    {{ substr($seance->heure_debut, 0, 5) }} – {{ substr($seance->heure_fin, 0, 5) }}
                @else
                    —
                @endif
            </dd>
        </div>
        <div class="sa-champ">
            <dt>Formateur</dt>
            <dd>{{ $seance->formateur?->name ?? '—' }}</dd>
        </div>
        <div class="sa-champ">
            <dt>Statut</dt>
            <dd><span class="sa-badge sa-badge--{{ $seance->statut->value }}">{{ $seance->statut->getLabel() }}</span></dd>
        </div>
        <div class="sa-champ">
            <dt>Effectif</dt>
            <dd>{{ $seance->presences->count() }} apprenant{{ $seance->presences->count() > 1 ? 's' : '' }}</dd>
        </div>
        <div class="sa-champ">
            <dt>Assiduité</dt>
            <dd>
                @php $taux = $seance->tauxPresence(); @endphp
                @if ($taux === null)
                    <span style="color: rgb(148 163 184);">Non émargée</span>
                @else
                    <span class="sa-taux {{ $taux >= 90 ? 'sa-taux--ok' : ($taux >= 75 ? 'sa-taux--alerte' : 'sa-taux--critique') }}">{{ $taux }} %</span>
                @endif
            </dd>
        </div>
    </dl>
</div>
