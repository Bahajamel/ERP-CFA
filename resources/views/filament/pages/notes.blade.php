<x-filament-panels::page>
    @php
        $nombre = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
        $couleur = fn ($m) => $m === null ? 'nt-muted' : ($m >= 14 ? 'nt-ok' : ($m >= 10 ? 'nt-mid' : 'nt-low'));
    @endphp

    <style>
        .nt-bar select {
            min-width: 22rem; padding: .55rem .8rem; border-radius: .6rem; font-size: .9rem;
            border: 1px solid rgb(209 213 219); background: #fff; color: rgb(17 24 39);
        }
        .dark .nt-bar select { border-color: rgb(55 65 81); background: rgb(17 24 39); color: #fff; }
        .nt-bar label { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: rgb(100 116 139); margin-bottom: .3rem; }

        /* Matières (chips cliquables) */
        .nt-mats { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.25rem; }
        .nt-chip {
            display: inline-flex; align-items: center; gap: .45rem; cursor: pointer;
            padding: .55rem .9rem; border-radius: .7rem; font-size: .85rem; font-weight: 600;
            border: 1px solid rgb(226 232 240); background: #fff; color: rgb(30 41 59); transition: all .12s ease;
        }
        .nt-chip:hover { border-color: rgb(129 140 248); transform: translateY(-1px); }
        .dark .nt-chip { border-color: rgb(55 65 81); background: rgb(30 41 59); color: rgb(226 232 240); }
        .nt-chip--on { background: linear-gradient(135deg,#4f46e5,#7c3aed); color: #fff; border-color: transparent; box-shadow: 0 8px 18px -8px rgba(79,70,229,.6); }
        .nt-chip-nb { font-size: .68rem; font-weight: 800; padding: .05rem .4rem; border-radius: 999px; background: rgb(238 242 255); color: rgb(67 56 202); }
        .nt-chip--on .nt-chip-nb { background: rgba(255,255,255,.22); color: #fff; }
        .nt-chip-nb--0 { background: rgb(241 245 249); color: rgb(148 163 184); }

        /* Tableau des notes */
        .nt-panel { margin-top: 1.25rem; border: 1px solid rgb(226 232 240); border-radius: .9rem; overflow: hidden; background: #fff; }
        .dark .nt-panel { border-color: rgb(55 65 81); background: rgb(15 23 42); }
        .nt-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1.1rem; border-bottom: 1px solid rgb(226 232 240); }
        .dark .nt-head { border-color: rgb(55 65 81); }
        .nt-head-t { font-size: 1rem; font-weight: 800; color: rgb(15 23 42); }
        .dark .nt-head-t { color: #fff; }
        .nt-head-s { font-size: .78rem; color: rgb(100 116 139); }

        table.nt-table { width: 100%; border-collapse: collapse; }
        table.nt-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: rgb(100 116 139); padding: .6rem 1.1rem; border-bottom: 1px solid rgb(226 232 240); }
        .dark table.nt-table th { border-color: rgb(55 65 81); }
        table.nt-table td { padding: .7rem 1.1rem; border-bottom: 1px solid rgb(241 245 249); font-size: .9rem; vertical-align: middle; }
        .dark table.nt-table td { border-color: rgb(30 41 59); }
        table.nt-table tr:last-child td { border-bottom: 0; }
        .nt-name { font-weight: 700; color: rgb(15 23 42); }
        .dark .nt-name { color: #fff; }

        .nt-notes { display: flex; flex-wrap: wrap; gap: .35rem; }
        .nt-note { display: inline-flex; align-items: center; gap: .2rem; text-decoration: none; font-size: .8rem; font-weight: 700; padding: .18rem .5rem; border-radius: .45rem; border: 1px solid transparent; }
        .nt-note-clip { width: .72rem; height: .72rem; opacity: .75; }
        .nt-ok  { background: rgb(209 250 229); color: rgb(4 120 87); }
        .nt-mid { background: rgb(254 243 199); color: rgb(180 83 9); }
        .nt-low { background: rgb(254 226 226); color: rgb(185 28 28); }
        .nt-muted { color: rgb(148 163 184); font-style: italic; font-weight: 500; }
        a.nt-note:hover { border-color: currentColor; }
        .nt-moy { font-weight: 800; font-size: .95rem; }

        .nt-head-actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }

        /* Colonne « Examen (preuve) » — par apprenant */
        .nt-ic-sm { width: .95rem; height: .95rem; }
        .nt-proof-cell { display: inline-flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: .3rem; }
        .nt-proof-item { display: inline-flex; align-items: center; gap: .15rem; }
        /* Une note dont la copie d'examen existe = cliquable vers la copie (trombone + contour) */
        .nt-note--proof { box-shadow: 0 0 0 1.5px currentColor inset; cursor: pointer; }
        .nt-proof { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; padding: .3rem .6rem; border-radius: .5rem; cursor: pointer; text-decoration: none; border: 1px solid transparent; }
        .nt-proof--add { color: rgb(79 70 229); background: rgb(238 242 255); border-color: rgb(224 231 255); }
        .nt-proof--add:hover { background: rgb(224 231 255); }
        .dark .nt-proof--add { color: rgb(165 180 252); background: rgba(79,70,229,.15); border-color: rgba(129,140,248,.25); }
        .nt-proof--ok { color: rgb(4 120 87); background: rgb(209 250 229); border-color: rgb(167 243 208); }
        .nt-proof--ok:hover { background: rgb(167 243 208); }
        .dark .nt-proof--ok { color: rgb(110 231 183); background: rgba(16,185,129,.15); border-color: rgba(52,211,153,.25); }
        .nt-proof-mini { display: inline-flex; align-items: center; padding: .3rem .35rem; border: 1px solid rgb(226 232 240); background: #fff; color: rgb(100 116 139); border-radius: .45rem; cursor: pointer; }
        .dark .nt-proof-mini { border-color: rgb(55 65 81); background: rgb(30 41 59); color: rgb(148 163 184); }
        .nt-proof-mini:hover { background: rgb(238 242 255); color: rgb(67 56 202); }
        .nt-proof-mini--del:hover { background: rgb(254 226 226); color: rgb(185 28 28); }

        .nt-empty { padding: 2.5rem 1rem; text-align: center; color: rgb(148 163 184); }
        .nt-hint { margin-top: 1.5rem; padding: 2rem; text-align: center; color: rgb(148 163 184); border: 1.5px dashed rgb(226 232 240); border-radius: .9rem; }
        .dark .nt-hint { border-color: rgb(55 65 81); }
    </style>

    {{-- 1) Choix de la classe --}}
    <div class="nt-bar">
        <label for="nt-classe">Classe (formation & année)</label>
        <select id="nt-classe" wire:model.live="promotionId">
            @foreach ($classes as $id => $libelle)
                <option value="{{ $id }}">{{ $libelle }}</option>
            @endforeach
        </select>
    </div>

    @if ($classe)
        {{-- 2) Matières de la classe --}}
        @if ($matieres->isEmpty())
            <div class="nt-hint">
                Aucune matière au programme de cette formation.<br>
                Ajoutez-les depuis la fiche <strong>Formation</strong> (catalogue).
            </div>
        @else
            <div class="nt-mats">
                @foreach ($matieres as $m)
                    <button type="button"
                        class="nt-chip {{ $matiere === $m['nom'] ? 'nt-chip--on' : '' }}"
                        wire:click="choisirMatiere(@js($m['nom']))">
                        {{ $m['nom'] }}
                        <span class="nt-chip-nb {{ $m['nb'] === 0 ? 'nt-chip-nb--0' : '' }}">{{ $m['nb'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- 3) Notes des inscrits pour la matière choisie --}}
            @if ($matiere)
                <div class="nt-panel">
                    <div class="nt-head">
                        <div>
                            <div class="nt-head-t">{{ $matiere }}</div>
                            <div class="nt-head-s">{{ $classe->nom_complet }} · {{ $lignes->count() }} inscrit{{ $lignes->count() > 1 ? 's' : '' }}</div>
                        </div>
                        <div class="nt-head-actions">
                            {{ $this->nouvelleEpreuveAction }}
                        </div>
                    </div>

                    @if ($lignes->isEmpty())
                        <div class="nt-empty">Aucun apprenant inscrit dans cette classe.</div>
                    @else
                        <table class="nt-table">
                            <thead>
                                <tr>
                                    <th>Apprenant</th>
                                    <th>Notes</th>
                                    <th style="text-align:right">Moyenne / 20</th>
                                    <th style="text-align:right">Examen (preuve)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lignes as $ligne)
                                    <tr>
                                        <td class="nt-name">{{ $ligne['apprenant']->nom_complet }}</td>
                                        <td>
                                            @if ($ligne['notes']->isEmpty())
                                                <span class="nt-muted">Pas encore de note</span>
                                            @else
                                                <div class="nt-notes">
                                                    @foreach ($ligne['notes'] as $note)
                                                        @php $exUrl = $ligne['copiesParType'][$note->type?->value]['url'] ?? null; @endphp
                                                        <a class="nt-note {{ $couleur($note->noteSur20()) }} {{ $exUrl ? 'nt-note--proof' : '' }}"
                                                           href="{{ $exUrl ?? $urlEdition($note) }}"
                                                           @if ($exUrl) target="_blank" @endif
                                                           title="{{ $note->type?->getLabel() }} · coef {{ $nombre($note->coefficient) }} · {{ $note->date?->format('d/m/Y') }} · {{ $exUrl ? 'ouvrir la copie d\'examen' : 'aucune copie déposée — cliquer pour modifier' }}">
                                                            {{ $nombre($note->note) }}@if ((float) $note->bareme != 20)/{{ $nombre($note->bareme) }}@endif
                                                            @if ($exUrl)<x-filament::icon icon="heroicon-m-paper-clip" class="nt-note-clip" />@endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td style="text-align:right">
                                            <span class="nt-moy {{ $couleur($ligne['moyenne']) }}">
                                                {{ $ligne['moyenne'] !== null ? $nombre($ligne['moyenne']) : '—' }}
                                            </span>
                                        </td>
                                        <td style="text-align:right">
                                            @php $cid = $ligne['apprenant']->id; @endphp
                                            <div class="nt-proof-cell">
                                                @foreach ($ligne['copies'] as $cp)
                                                    <span class="nt-proof-item">
                                                        <a href="{{ $cp['url'] }}" target="_blank" class="nt-proof nt-proof--ok" title="{{ $cp['nom'] }}">
                                                            <x-filament::icon icon="heroicon-o-paper-clip" class="nt-ic-sm" /> {{ $cp['type'] }}
                                                        </a>
                                                        <button type="button" class="nt-proof-mini nt-proof-mini--del" title="Retirer la copie"
                                                            wire:click="mountAction('retirerExamenApprenant', { candidate: {{ $cid }}, type: @js($cp['type_value']) })">
                                                            <x-filament::icon icon="heroicon-o-x-mark" class="nt-ic-sm" />
                                                        </button>
                                                    </span>
                                                @endforeach
                                                <button type="button" class="nt-proof nt-proof--add" title="Importer une copie d'examen"
                                                    wire:click="mountAction('importerExamenApprenant', { candidate: {{ $cid }} })">
                                                    <x-filament::icon icon="heroicon-o-arrow-up-tray" class="nt-ic-sm" /> {{ $ligne['copies']->isEmpty() ? 'Importer' : 'Ajouter' }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @else
                <div class="nt-hint">Choisissez une matière ci-dessus pour voir les notes des inscrits.</div>
            @endif
        @endif
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
