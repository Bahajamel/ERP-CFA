<div>
    <style>
        .fa-entete { display: flex; gap: 1rem; align-items: center; }
        .fa-photo, .fa-initiales {
            width: 4.5rem; height: 4.5rem; border-radius: 9999px; flex: none;
            object-fit: cover; box-shadow: 0 0 0 3px rgb(99 102 241 / .25);
        }
        .fa-initiales {
            display: flex; align-items: center; justify-content: center;
            background: rgb(238 242 255); color: rgb(79 70 229); font-weight: 800; font-size: 1.4rem;
        }
        .dark .fa-initiales { background: rgb(49 46 129 / .4); color: rgb(165 180 252); }
        .fa-nom { font-size: 1.05rem; font-weight: 800; color: rgb(17 24 39); }
        .dark .fa-nom { color: #fff; }
        .fa-sous { font-size: .8rem; color: rgb(107 114 128); margin-top: .1rem; }
        .dark .fa-sous { color: rgb(156 163 175); }

        .fa-grille { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem 1rem; margin-top: 1rem; }
        @media (max-width: 560px) { .fa-grille { grid-template-columns: 1fr; } }
        .fa-champ dt { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: rgb(156 163 175); }
        .fa-champ dd { font-size: .85rem; color: rgb(31 41 55); margin-top: .1rem; }
        .dark .fa-champ dd { color: rgb(229 231 235); }

        .fa-badges { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .2rem; }
        .fa-badge {
            font-size: .7rem; font-weight: 600; padding: .15rem .55rem; border-radius: 999px;
            background: rgb(238 242 255); color: rgb(67 56 202);
        }
        .dark .fa-badge { background: rgb(49 46 129 / .4); color: rgb(165 180 252); }

        .fa-taux { font-weight: 800; }
        .fa-taux--ok { color: rgb(5 150 105); }
        .fa-taux--alerte { color: rgb(217 119 6); }
        .fa-taux--critique { color: rgb(220 38 38); }

        .fa-section-titre { font-size: .8rem; font-weight: 700; color: rgb(55 65 81); margin: 1.1rem 0 .4rem; }
        .dark .fa-section-titre { color: rgb(209 213 219); }
        .fa-docs { display: flex; flex-direction: column; gap: .35rem; }
        .fa-doc {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .45rem .65rem; border: 1px solid rgb(229 231 235); border-radius: .5rem;
            font-size: .8rem; color: rgb(31 41 55); text-decoration: none; background: rgb(249 250 251);
        }
        .fa-doc:hover { border-color: rgb(165 180 252); }
        .dark .fa-doc { border-color: rgb(55 65 81); background: rgb(31 41 55); color: rgb(229 231 235); }
        .fa-doc-date { font-size: .7rem; color: rgb(156 163 175); flex: none; }
        .fa-vide { font-size: .78rem; color: rgb(156 163 175); font-style: italic; }
    </style>

    {{-- En-tête : photo + identité --}}
    <div class="fa-entete">
        @if ($photo = $apprenant->getFirstMediaUrl('photo'))
            <img src="{{ $photo }}" alt="Photo de {{ $apprenant->nom_complet }}" class="fa-photo">
        @else
            <div class="fa-initiales">{{ mb_substr($apprenant->prenom ?? '', 0, 1) }}{{ mb_substr($apprenant->nom ?? '', 0, 1) }}</div>
        @endif
        <div>
            <div class="fa-nom">{{ $apprenant->nom_complet }}</div>
            <div class="fa-sous">{{ $apprenant->formationVisee?->libelle ?? 'Formation non renseignée' }}</div>
            <div class="fa-sous">{{ $apprenant->statut?->getLabel() ?? '' }}</div>
        </div>
    </div>

    {{-- Informations --}}
    <dl class="fa-grille">
        <div class="fa-champ">
            <dt>Date de naissance</dt>
            <dd>
                {{ $apprenant->date_naissance?->format('d/m/Y') ?? '—' }}
                @if ($apprenant->date_naissance) ({{ $apprenant->date_naissance->age }} ans) @endif
            </dd>
        </div>
        <div class="fa-champ">
            <dt>Assiduité</dt>
            <dd>
                @if ($assiduite === null)
                    <span class="fa-vide">Aucune présence renseignée</span>
                @else
                    <span class="fa-taux {{ $assiduite >= 90 ? 'fa-taux--ok' : ($assiduite >= 75 ? 'fa-taux--alerte' : 'fa-taux--critique') }}">
                        {{ $assiduite }} %
                    </span>
                @endif
            </dd>
        </div>
        <div class="fa-champ">
            <dt>Email</dt>
            <dd>{{ $apprenant->email ?? '—' }}</dd>
        </div>
        <div class="fa-champ">
            <dt>Téléphone</dt>
            <dd>{{ $apprenant->telephone ?? '—' }}</dd>
        </div>
        <div class="fa-champ" style="grid-column: 1 / -1;">
            <dt>Classe</dt>
            <dd>
                @if ($apprenant->promotions->isEmpty())
                    <span class="fa-vide">Aucune classe</span>
                @else
                    <div class="fa-badges" style="flex-direction:column;align-items:flex-start;gap:.4rem;">
                        @foreach ($apprenant->promotions as $classe)
                            <div>
                                <span class="fa-badge">{{ $classe->nom_complet }}</span>
                                @php $mats = $classe->pivot->matieres ?? []; @endphp
                                @if (! empty($mats))
                                    @foreach ($mats as $mat)
                                        <span class="fa-badge" style="background:#eef2ff;color:#4338ca;">{{ $mat }}</span>
                                    @endforeach
                                @elseif ($classe->pivot->invited_at && ! $classe->pivot->responded_at)
                                    <span class="fa-vide">— en attente du choix des matières</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </dd>
        </div>
    </dl>

    {{-- Documents pédagogiques --}}
    <div class="fa-section-titre">Documents pédagogiques ({{ $documents->count() }})</div>
    <div class="fa-docs">
        @forelse ($documents as $document)
            <a class="fa-doc" href="{{ \App\Support\SecureMedia::pour($document, 'fichier') }}" target="_blank" rel="noopener">
                <span>📄 {{ $document->nom_fichier }}</span>
                <span class="fa-doc-date">{{ $document->created_at->format('d/m/Y') }}</span>
            </a>
        @empty
            <span class="fa-vide">Aucun document — déposez bulletins et relevés de notes ci-dessous.</span>
        @endforelse
    </div>
</div>
