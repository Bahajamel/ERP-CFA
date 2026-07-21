@php
    $need = $this->need;
    $nbSel = count($selection);
    // Couleur + libellé du score de compatibilité.
    $scoreMeta = function (int $s): array {
        return match (true) {
            $s >= 80 => ['#16a34a', 'Très compatible'],
            $s >= 60 => ['#2563eb', 'Compatible'],
            $s >= 40 => ['#f59e0b', 'À vérifier'],
            default => ['#ef4444', 'Non recommandé'],
        };
    };
@endphp

<div class="pc">
    <style>
        .pc { --pc-line: rgba(15,23,42,.08); display: flex; flex-direction: column; gap: 1rem; }
        .dark .pc { --pc-line: rgba(255,255,255,.1); }
        .pc-card { background: #fff; border: 1px solid var(--pc-line); border-radius: .9rem; }
        .dark .pc-card { background: #18202f; }
        .pc-cardhead { padding: .8rem 1rem; border-bottom: 1px solid var(--pc-line); font-weight: 700; color: #0f172a; }
        .dark .pc-cardhead { color: #f1f5f9; }

        /* Carte besoin */
        .pc-need { display: flex; flex-wrap: wrap; gap: 1.2rem 2rem; align-items: center; padding: 1rem 1.2rem;
            background: #eff6ff; border: 1px solid #dbeafe; border-radius: .9rem; }
        .dark .pc-need { background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.3); }
        .pc-need-ico { display: grid; place-items: center; width: 3rem; height: 3rem; border-radius: .7rem;
            background: #2563eb; color: #fff; flex: none; }
        .pc-need-ico svg { width: 1.5rem; height: 1.5rem; }
        .pc-need-item { display: flex; flex-direction: column; gap: .1rem; }
        .pc-need-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; font-weight: 600; }
        .pc-need-val { font-size: .9rem; font-weight: 600; color: #0f172a; }
        .dark .pc-need-val { color: #e2e8f0; }
        .pc-badge-vert { margin-left: auto; background: #dcfce7; color: #15803d; font-weight: 700; font-size: .75rem;
            padding: .3rem .7rem; border-radius: 9999px; display: inline-flex; align-items: center; gap: .35rem; }
        .dark .pc-badge-vert { background: rgba(34,197,94,.18); color: #86efac; }

        /* Grille */
        .pc-grid { display: grid; grid-template-columns: 64% 36%; gap: 1rem; align-items: start; }
        .pc-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }

        /* Filtres */
        .pc-filters { display: flex; flex-wrap: wrap; gap: .5rem; padding: .8rem 1rem; }
        .pc-input, .pc-select { padding: .5rem .65rem; border: 1px solid var(--pc-line); border-radius: .55rem;
            font-size: .82rem; background: #fff; color: #334155; }
        .dark .pc-input, .dark .pc-select { background: #0f1725; color: #cbd5e1; }
        .pc-input { flex: 1; min-width: 160px; }
        .pc-input:focus, .pc-select:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

        /* Carte candidat */
        .pc-cands { display: flex; flex-direction: column; gap: .7rem; padding: 0 1rem 1rem; }
        .pc-cand { display: grid; grid-template-columns: auto 1fr auto auto auto; gap: 1rem; align-items: center;
            padding: .8rem; border: 1px solid var(--pc-line); border-radius: .8rem; background: #fff; }
        .dark .pc-cand { background: #1a2333; }
        .pc-cand.is-sel { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 1px #2563eb; }
        .dark .pc-cand.is-sel { background: rgba(37,99,235,.12); }
        .pc-check { width: 1.15rem; height: 1.15rem; accent-color: #2563eb; cursor: pointer; }
        .pc-id { display: flex; align-items: center; gap: .7rem; min-width: 0; }
        .pc-avatar { display: grid; place-items: center; width: 2.6rem; height: 2.6rem; border-radius: 9999px; flex: none;
            background: #e0e7ff; color: #4338ca; font-weight: 700; font-size: .85rem; }
        .dark .pc-avatar { background: #312e81; color: #c7d2fe; }
        .pc-name { font-weight: 700; color: #0f172a; }
        .dark .pc-name { color: #f1f5f9; }
        .pc-sub { font-size: .76rem; color: #64748b; }
        .pc-tag { display: inline-flex; align-items: center; gap: .25rem; margin-top: .3rem; font-size: .72rem;
            color: #4338ca; background: #eef2ff; border: 1px solid #e0e7ff; padding: .12rem .45rem; border-radius: 9999px; }
        .dark .pc-tag { background: #312e81; color: #c7d2fe; border-color: #3730a3; }
        .pc-facts { font-size: .76rem; color: #475569; display: flex; flex-direction: column; gap: .2rem; }
        .dark .pc-facts { color: #cbd5e1; }
        .pc-facts b { color: #334155; font-weight: 600; } .dark .pc-facts b { color: #e2e8f0; }
        .pc-ok { color: #16a34a; } .pc-warn { color: #ea580c; }
        .pc-consent-warn { color: #b45309; font-weight: 600; }
        .dark .pc-consent-warn { color: #fdba74; }
        .pc-cand.is-bloque { opacity: .72; background: #fffbeb; border-color: #fde68a; }
        .dark .pc-cand.is-bloque { background: rgba(180,83,9,.1); border-color: rgba(180,83,9,.4); }

        /* Donut score */
        .pc-donut { position: relative; width: 3.4rem; height: 3.4rem; border-radius: 50%; display: grid; place-items: center;
            background: conic-gradient(var(--col) calc(var(--v) * 1%), #e5e7eb 0); }
        .dark .pc-donut { background: conic-gradient(var(--col) calc(var(--v) * 1%), #334155 0); }
        .pc-donut::before { content: ''; position: absolute; inset: .32rem; border-radius: 50%; background: #fff; }
        .dark .pc-donut::before { background: #1a2333; }
        .pc-donut span { position: relative; font-size: .78rem; font-weight: 800; color: var(--col); }
        .pc-score-lbl { font-size: .68rem; font-weight: 600; text-align: center; margin-top: .2rem; }

        .pc-btns { display: flex; flex-direction: column; gap: .35rem; }
        .pc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; padding: .4rem .7rem;
            border-radius: .55rem; font-size: .78rem; font-weight: 600; border: 1px solid var(--pc-line);
            background: #fff; color: #334155; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .dark .pc-btn { background: #18202f; color: #cbd5e1; }
        .pc-btn:hover { background: #f8fafc; } .dark .pc-btn:hover { background: #1f2937; }
        .pc-btn svg { width: .9rem; height: .9rem; }
        .pc-btn--sel { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
        .pc-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .pc-btn--primary:hover { background: #1d4ed8; }
        .pc-more { text-align: center; color: #2563eb; font-size: .82rem; font-weight: 600; cursor: pointer; padding: .3rem; }

        /* Résumé */
        .pc-sum { padding: 1rem; display: flex; flex-direction: column; gap: .9rem; }
        .pc-sum-ent { background: #f8fafc; border: 1px solid var(--pc-line); border-radius: .6rem; padding: .6rem .8rem;
            font-size: .82rem; color: #334155; } .dark .pc-sum-ent { background: #0f1725; color: #cbd5e1; }
        .pc-sum-count { display: flex; align-items: center; gap: .5rem; font-weight: 700; color: #0f172a; }
        .dark .pc-sum-count { color: #f1f5f9; }
        .pc-field-lbl { font-size: .78rem; font-weight: 600; color: #475569; margin-bottom: .3rem; }
        .dark .pc-field-lbl { color: #94a3b8; }
        .pc-radios { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; }
        .pc-radio { display: flex; align-items: center; gap: .4rem; padding: .45rem .6rem; border: 1px solid var(--pc-line);
            border-radius: .55rem; font-size: .8rem; color: #334155; cursor: pointer; } .dark .pc-radio { color: #cbd5e1; }
        .pc-radio.is-on { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; } .dark .pc-radio.is-on { background: rgba(37,99,235,.15); }
        .pc-textarea { width: 100%; padding: .55rem .7rem; border: 1px solid var(--pc-line); border-radius: .55rem;
            font-size: .82rem; background: #fff; color: #334155; resize: vertical; } .dark .pc-textarea { background: #0f1725; color: #cbd5e1; }
        .pc-confirm { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-size: .8rem; font-weight: 600;
            padding: .5rem .7rem; border-radius: .55rem; display: flex; align-items: center; gap: .4rem; }
        .dark .pc-confirm { background: rgba(34,197,94,.12); color: #86efac; border-color: rgba(34,197,94,.3); }
        .pc-help { font-size: .7rem; color: #94a3b8; text-align: right; }

        /* Barre d'action */
        .pc-bar { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding: .8rem 1rem; background: #fff; border: 1px solid var(--pc-line); border-radius: .8rem;
            box-shadow: 0 -4px 12px rgba(15,23,42,.05); flex-wrap: wrap; }
        .dark .pc-bar { background: #18202f; }
        .pc-bar-count { font-weight: 700; color: #0f172a; } .dark .pc-bar-count { color: #f1f5f9; }
        .pc-bar-actions { display: flex; gap: .5rem; }

        @media (max-width: 1024px) { .pc-grid { grid-template-columns: 1fr; } }
        @media (max-width: 640px) {
            .pc-cand { grid-template-columns: auto 1fr; }
            .pc-cand > .pc-facts, .pc-cand > div:nth-child(4) { grid-column: 2; }
            .pc-radios { grid-template-columns: 1fr; }
        }
    </style>

    {{-- Carte besoin --}}
    @if ($need)
        <div class="pc-need">
            <span class="pc-need-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
            </span>
            <div class="pc-need-item"><span class="pc-need-label">Entreprise</span><span class="pc-need-val">{{ $need->company?->raison_sociale ?? '—' }}</span></div>
            <div class="pc-need-item"><span class="pc-need-label">Besoin</span><span class="pc-need-val">{{ $need->intitule_poste }}</span></div>
            <div class="pc-need-item"><span class="pc-need-label">Formation</span><span class="pc-need-val">{{ $need->formation?->libelle ?? '—' }}</span></div>
            <div class="pc-need-item"><span class="pc-need-label">Postes ouverts</span><span class="pc-need-val">{{ $need->nb_postes }}</span></div>
            <div class="pc-need-item"><span class="pc-need-label">Lieu</span><span class="pc-need-val">{{ $need->localisation ?: '—' }}</span></div>
            <div class="pc-need-item"><span class="pc-need-label">Contact</span><span class="pc-need-val">{{ $need->contact?->nom_complet ?? '—' }}</span></div>
            <span class="pc-badge-vert">● Profils recherchés</span>
        </div>
    @endif

    <div class="pc-grid">
        {{-- Colonne gauche : candidats --}}
        <div class="pc-col">
            <div class="pc-card">
                <div class="pc-cardhead">Candidats compatibles</div>
                <div class="pc-filters">
                    <input type="text" class="pc-input" placeholder="Rechercher un candidat…" wire:model.live.debounce.300ms="recherche">
                    <select class="pc-select" wire:model.live="filtreDisponibilite">
                        <option value="">Disponibilité</option>
                        @foreach ($this->disponibilites as $dispo)<option value="{{ $dispo }}">{{ $dispo }}</option>@endforeach
                    </select>
                    <select class="pc-select" wire:model.live="filtreCv">
                        <option value="">CV disponible</option>
                        <option value="oui">CV présent</option>
                        <option value="non">CV manquant</option>
                    </select>
                </div>

                <div class="pc-cands">
                    @forelse ($this->candidats as $c)
                        @php [$col, $lbl] = $scoreMeta($c['score']); $sel = in_array($c['id'], $selection, true); @endphp
                        <div class="pc-cand {{ $sel ? 'is-sel' : '' }} {{ $c['consent'] ? '' : 'is-bloque' }}" wire:key="cand-{{ $c['id'] }}">
                            <input type="checkbox" class="pc-check" @checked($sel) @disabled(! $c['consent']) wire:click="toggle({{ $c['id'] }})">
                            <div class="pc-id">
                                <span class="pc-avatar">{{ $c['initiales'] }}</span>
                                <div>
                                    <div class="pc-name">{{ $c['nom'] }}</div>
                                    <div class="pc-sub">{{ $c['formation'] }} · {{ $c['statut'] }}</div>
                                    <span class="pc-tag">★ {{ $c['pointFort'] }}</span>
                                </div>
                            </div>
                            <div class="pc-facts">
                                <span><b>Mobilité</b> · {{ $c['mobilite'] }}</span>
                                <span><b>Dispo</b> · {{ $c['disponibilite'] }}</span>
                                <span class="{{ $c['cvDispo'] ? 'pc-ok' : 'pc-warn' }}"><b>CV</b> · {{ $c['cvDispo'] ? 'Disponible' : 'Non disponible' }}</span>
                                @unless ($c['consent'])
                                    <span class="pc-consent-warn">⚠ CV non autorisé — accord requis sur sa fiche</span>
                                @endunless
                            </div>
                            <div style="text-align:center">
                                <div class="pc-donut" style="--v: {{ $c['score'] }}; --col: {{ $col }}"><span>{{ $c['score'] }}%</span></div>
                                <div class="pc-score-lbl" style="color: {{ $col }}">{{ $lbl }}</div>
                            </div>
                            <div class="pc-btns">
                                <a class="pc-btn" href="{{ route('filament.admin.resources.candidates.edit', ['record' => $c['id']]) }}" target="_blank">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Voir fiche
                                </a>
                                @if (! $c['consent'])
                                    <span class="pc-btn" style="opacity:.5;cursor:not-allowed">⚠ CV non autorisé</span>
                                @else
                                    <button type="button" class="pc-btn {{ $sel ? 'pc-btn--sel' : '' }}" wire:click="toggle({{ $c['id'] }})">
                                        @if ($sel) ✓ Sélectionné @else + Sélectionner @endif
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p style="color:#94a3b8;font-size:.85rem;padding:1rem 0;text-align:center">Aucun candidat compatible (formation, disponibilité…) pour ce besoin.</p>
                    @endforelse

                    @if ($this->candidats->count() >= $limite)
                        <div class="pc-more" wire:click="chargerPlus">Charger plus de candidats ▾</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Colonne droite : résumé --}}
        <div class="pc-col">
            <div class="pc-card">
                <div class="pc-cardhead">Résumé de la proposition</div>
                <div class="pc-sum">
                    <div class="pc-sum-ent">
                        <div><b>Entreprise :</b> {{ $need?->company?->raison_sociale ?? '—' }}</div>
                        <div><b>Besoin :</b> {{ $need?->formation?->libelle ?? $need?->intitule_poste }}</div>
                    </div>

                    <div class="pc-sum-count">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        {{ $nbSel }} candidat{{ $nbSel > 1 ? 's' : '' }} sélectionné{{ $nbSel > 1 ? 's' : '' }}
                    </div>

                    <div>
                        <div class="pc-field-lbl">Canal de proposition</div>
                        <div class="pc-radios">
                            @foreach (['email' => 'Email', 'telephone' => 'Téléphone', 'sms' => 'SMS', 'autre' => 'Autre'] as $val => $lib)
                                <label class="pc-radio {{ $canal === $val ? 'is-on' : '' }}">
                                    <input type="radio" value="{{ $val }}" wire:model.live="canal"> {{ $lib }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="pc-field-lbl">Date de relance</div>
                        <input type="date" class="pc-textarea" wire:model="dateRelance">
                    </div>

                    <div>
                        <div class="pc-field-lbl">Responsable du suivi</div>
                        <select class="pc-textarea" wire:model="responsableId">
                            @foreach ($this->responsables as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach
                        </select>
                    </div>

                    <div>
                        <div class="pc-field-lbl">Commentaire interne (optionnel)</div>
                        <textarea class="pc-textarea" rows="2" maxlength="300" placeholder="Notes internes visibles uniquement par l'équipe." wire:model="commentaire"></textarea>
                        <div class="pc-help">{{ mb_strlen($commentaire) }}/300</div>
                    </div>

                    <div>
                        <div class="pc-field-lbl">Mail type</div>
                        <select class="pc-textarea" wire:model.live="templateId">
                            <option value="">Message par défaut</option>
                            @foreach ($this->modelesEmail as $id => $nom)
                                <option value="{{ $id }}">{{ $nom }}</option>
                            @endforeach
                        </select>
                        <div class="pc-help">
                            Choisissez un modèle : le message ci-dessous est rempli avec les infos de l'offre (entreprise, poste, formation…).
                        </div>
                    </div>

                    <div>
                        <div class="pc-field-lbl">Message de présentation</div>
                        <textarea class="pc-textarea" rows="6" wire:model="message"></textarea>
                    </div>

                    <button type="button" class="pc-btn pc-btn--primary" style="padding:.6rem" wire:click="valider">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        Valider la proposition
                    </button>
                    <button type="button" class="pc-btn" style="padding:.6rem" wire:click="brouillon">Enregistrer comme brouillon</button>

                    @if ($nbSel > 0)
                        <div class="pc-confirm">✓ {{ $nbSel }} candidat{{ $nbSel > 1 ? 's' : '' }} prêt{{ $nbSel > 1 ? 's' : '' }} à être proposé{{ $nbSel > 1 ? 's' : '' }}.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Barre d'action --}}
    <div class="pc-bar">
        <span class="pc-bar-count">{{ $nbSel }} candidat{{ $nbSel > 1 ? 's' : '' }} sélectionné{{ $nbSel > 1 ? 's' : '' }}</span>
        <div class="pc-bar-actions">
            <button type="button" class="pc-btn" wire:click="brouillon">Brouillon</button>
            <button type="button" class="pc-btn pc-btn--primary" wire:click="valider">Valider la proposition</button>
        </div>
    </div>
</div>
