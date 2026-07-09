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

        /* ---------- Apprenants prévus ---------- */
        .sa-sec { margin-top: 1.15rem; }
        .sa-sec-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .55rem; }
        .sa-sec-bar { width: .28rem; height: 1.1rem; border-radius: 999px; background: linear-gradient(#6366f1, #8b5cf6); }
        .sa-sec-title { font-size: .95rem; font-weight: 800; color: rgb(15 23 42); }
        .dark .sa-sec-title { color: #fff; }
        .sa-sec-count { font-size: .72rem; font-weight: 700; color: rgb(100 116 139); background: rgb(248 250 252); border: 1px solid rgb(226 232 240); padding: .12rem .55rem; border-radius: 999px; }
        .dark .sa-sec-count { color: rgb(148 163 184); background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }

        /* Zone défilante : tient de grands effectifs sans agrandir le pop-up. */
        .sa-list { max-height: 38vh; overflow-y: auto; display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .45rem; padding: .6rem; border: 1px solid rgb(226 232 240); border-radius: .7rem; background: rgb(248 250 252); }
        @media (max-width: 640px) { .sa-list { grid-template-columns: 1fr; } }
        .dark .sa-list { border-color: rgba(255,255,255,.08); background: rgba(255,255,255,.03); }
        .sa-appr { display: flex; align-items: center; gap: .6rem; padding: .4rem .55rem; border-radius: .5rem; background: #fff; border: 1px solid rgb(226 232 240); }
        .dark .sa-appr { background: rgb(15 23 42); border-color: rgba(255,255,255,.08); }
        .sa-ava { width: 1.95rem; height: 1.95rem; flex: none; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 800; color: #4f46e5; background: rgb(238 242 255); }
        .dark .sa-ava { color: rgb(165 180 252); background: rgb(49 46 129 / .4); }
        .sa-appr-nom { font-size: .86rem; font-weight: 600; color: rgb(15 23 42); }
        .dark .sa-appr-nom { color: rgb(226 232 240); }
        .sa-vide { font-size: .82rem; color: rgb(148 163 184); font-style: italic; padding: .5rem 0; }
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

    {{-- Apprenants prévus à cette séance (liste défilante, sans émargement) --}}
    @php
        $apprenants = $seance->presences
            ->map(fn ($p) => $p->candidate)
            ->filter()
            ->sortBy(fn ($c) => mb_strtolower(($c->nom ?? '').' '.($c->prenom ?? '')))
            ->values();
    @endphp
    <div class="sa-sec">
        <div class="sa-sec-head">
            <span class="sa-sec-bar"></span>
            <span class="sa-sec-title">Apprenants prévus</span>
            <span class="sa-sec-count">{{ $apprenants->count() }}</span>
        </div>

        @if ($apprenants->isEmpty())
            <p class="sa-vide">Aucun apprenant prévu pour cette séance.</p>
        @else
            <div class="sa-list">
                @foreach ($apprenants as $c)
                    <div class="sa-appr">
                        <span class="sa-ava">{{ mb_strtoupper(mb_substr($c->prenom ?? '', 0, 1).mb_substr($c->nom ?? '', 0, 1)) }}</span>
                        <span class="sa-appr-nom">{{ trim(($c->prenom ?? '').' '.($c->nom ?? '')) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
