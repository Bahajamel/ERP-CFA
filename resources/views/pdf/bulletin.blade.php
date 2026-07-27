<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .wrap { padding: 28px 34px; }

        .head { border-bottom: 3px solid #4f46e5; padding-bottom: 12px; margin-bottom: 18px; }
        .head table { width: 100%; }
        .cfa-nom { font-size: 16px; font-weight: bold; color: #312e81; }
        .cfa-meta { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .t { font-size: 20px; font-weight: bold; color: #4f46e5; letter-spacing: .5px; }
        .doc-title .s { font-size: 10px; color: #6b7280; margin-top: 3px; }

        .infos { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .infos td { padding: 5px 8px; font-size: 11px; vertical-align: top; }
        .infos .lbl { color: #6b7280; text-transform: uppercase; font-size: 8.5px; letter-spacing: .4px; }
        .infos .val { font-weight: bold; color: #111827; }
        .box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; }

        table.notes { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.notes th { background: #4f46e5; color: #fff; font-size: 10px; text-align: left; padding: 8px 10px; }
        table.notes th.c, table.notes td.c { text-align: center; }
        table.notes td { padding: 8px 10px; border-bottom: 1px solid #eef2f7; font-size: 11.5px; }
        table.notes tr:nth-child(even) td { background: #fafbff; }
        .mat { font-weight: bold; color: #1f2937; }
        .moy { font-weight: bold; }
        .moy-ok { color: #059669; } .moy-mid { color: #d97706; } .moy-low { color: #dc2626; }

        .synth { margin-top: 16px; width: 100%; border-collapse: collapse; }
        .synth td { width: 50%; padding: 0 6px; vertical-align: top; }
        .card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 14px; }
        .card .k { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .card .big { font-size: 24px; font-weight: bold; margin-top: 3px; }
        .card--moy { background: #eef2ff; border-color: #c7d2fe; }
        .card--moy .big { color: #4338ca; }
        .card--ass .big { color: #0f766e; }

        .empty { padding: 26px; text-align: center; color: #9ca3af; font-style: italic; border: 1px dashed #e5e7eb; border-radius: 6px; }
        .foot { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
@php
    $noteClass = fn ($m) => $m >= 14 ? 'moy-ok' : ($m >= 10 ? 'moy-mid' : 'moy-low');
    $nombre = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
@endphp
<div class="wrap">
    <div class="head">
        <table>
            <tr>
                <td>
                    <div class="cfa-nom">{{ $d['cfa']->nom ?? $d['cfa']->raison_sociale ?? 'Centre de Formation' }}</div>
                    <div class="cfa-meta">
                        {{ trim(($d['cfa']->adresse ?? '').' '.($d['cfa']->code_postal ?? '').' '.($d['cfa']->ville ?? ''), ' ') ?: '' }}
                        @if ($d['cfa']->nda) · Déclaration d'activité : {{ $d['cfa']->nda }} @endif
                    </div>
                </td>
                <td class="doc-title">
                    <div class="t">BULLETIN DE NOTES</div>
                    <div class="s">Édité le {{ $d['edite_le']->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="infos box">
        <tr>
            <td style="width:34%"><div class="lbl">Apprenant</div><div class="val">{{ $d['apprenant']->nom_complet }}</div></td>
            <td style="width:33%"><div class="lbl">Formation</div><div class="val">{{ $d['formation']->libelle ?? '—' }}</div></td>
            <td style="width:33%"><div class="lbl">Classe</div><div class="val">{{ $d['classe']?->nom_complet ?? '—' }}</div></td>
        </tr>
        <tr>
            <td>
                <div class="lbl">Date de naissance</div>
                <div class="val">{{ $d['apprenant']->date_naissance?->format('d/m/Y') ?? '—' }}</div>
            </td>
            <td>
                <div class="lbl">Année scolaire</div>
                <div class="val">{{ $d['classe']?->annee_scolaire ?? '—' }}</div>
            </td>
            <td>
                <div class="lbl">Nombre de matières évaluées</div>
                <div class="val">{{ count($d['matieres']) }}</div>
            </td>
        </tr>
    </table>

    @if (count($d['matieres']))
        <table class="notes">
            <thead>
                <tr>
                    <th>Matière</th>
                    <th class="c" style="width:80px">Évaluations</th>
                    <th class="c" style="width:80px">Coefficient</th>
                    <th class="c" style="width:110px">Moyenne / 20</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($d['matieres'] as $m)
                    <tr>
                        <td class="mat">{{ $m['matiere'] }}</td>
                        <td class="c">{{ $m['nb'] }}</td>
                        <td class="c">{{ $nombre($m['coefficient']) }}</td>
                        <td class="c moy {{ $noteClass($m['moyenne']) }}">{{ $nombre($m['moyenne']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="synth">
            <tr>
                <td>
                    <div class="card card--moy">
                        <div class="k">Moyenne générale</div>
                        <div class="big">{{ $d['moyenne_generale'] !== null ? $nombre($d['moyenne_generale']).' / 20' : '—' }}</div>
                    </div>
                </td>
                <td>
                    <div class="card card--ass">
                        <div class="k">Assiduité</div>
                        <div class="big">{{ $d['assiduite'] !== null ? $d['assiduite'].' %' : 'N/A' }}</div>
                        @if ($d['absences_injustifiees'] > 0)
                            <div style="font-size:9px;color:#dc2626;margin-top:2px;">{{ $d['absences_injustifiees'] }} absence(s) injustifiée(s)</div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    @else
        <div class="empty">Aucune note enregistrée pour cet apprenant.</div>
    @endif

    <div class="foot">
        Document généré par l'ERP du CFA — {{ $d['cfa']->nom ?? '' }} · {{ $d['edite_le']->format('d/m/Y à H:i') }}
    </div>
</div>
</body>
</html>
