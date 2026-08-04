<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; }
        .wrap { padding: 26px 30px; }

        .head { border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 16px; }
        .head table { width: 100%; }
        .head td { vertical-align: middle; }
        .logo img { max-height: 46px; max-width: 150px; }
        .cfa-nom { font-size: 15px; font-weight: bold; color: #312e81; }
        .cfa-meta { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .t { font-size: 19px; font-weight: bold; color: #4f46e5; letter-spacing: .5px; }
        .doc-title .s { font-size: 9px; color: #6b7280; margin-top: 3px; }

        .infos { width: 100%; border-collapse: collapse; margin-bottom: 14px;
                 background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; }
        .infos td { padding: 6px 9px; font-size: 10.5px; vertical-align: top; width: 50%; }
        .infos .lbl { color: #6b7280; text-transform: uppercase; font-size: 8px; letter-spacing: .4px; }
        .infos .val { font-weight: bold; color: #111827; }

        table.emarge { width: 100%; border-collapse: collapse; }
        table.emarge th { background: #4f46e5; color: #fff; font-size: 9.5px; text-align: left;
                          padding: 7px 8px; border: 1px solid #4338ca; }
        table.emarge th.c { text-align: center; }
        table.emarge td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 10.5px; vertical-align: top; }
        table.emarge tr:nth-child(even) td { background: #fafbff; }
        .num { text-align: center; color: #9ca3af; font-size: 9px; }
        .appr { font-weight: bold; color: #1f2937; }
        .ent { color: #6b7280; font-size: 9.5px; }
        .obs { color: #9ca3af; font-size: 8.5px; font-style: italic; margin-top: 2px; }
        .statut { font-weight: bold; font-size: 9.5px; }
        .sign-cell { height: 30px; }

        .zone { margin-top: 16px; width: 100%; border-collapse: collapse; }
        .zone td { vertical-align: top; padding: 0 6px; }
        .obs-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; min-height: 62px; }
        .obs-box .k { font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; margin-bottom: 5px; }
        .sign-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; min-height: 62px; }
        .sign-box .k { font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
        .sign-box .who { font-weight: bold; font-size: 10.5px; margin-top: 2px; }

        .fait { margin-top: 12px; font-size: 10px; color: #374151; }
        .foot { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 7px;
                font-size: 8.5px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
@php $cfa = $d['cfa']; @endphp
<div class="wrap">

    {{-- En-tête : identité CFA + titre --}}
    <div class="head">
        <table>
            <tr>
                <td style="width:60%">
                    @if ($d['logo'])
                        <div class="logo"><img src="{{ $d['logo'] }}" alt=""></div>
                    @endif
                    <div class="cfa-nom">{{ $cfa->designation() }}</div>
                    <div class="cfa-meta">
                        {{ trim(($cfa->adresse ?? '').' '.($cfa->code_postal ?? '').' '.($cfa->ville ?? ''), ' ') ?: '' }}
                        @if ($cfa->nda) · Déclaration d'activité : {{ $cfa->nda }} @endif
                    </div>
                </td>
                <td class="doc-title">
                    <div class="t">FICHE D'ÉMARGEMENT</div>
                    <div class="s">Éditée le {{ $d['edite_le']->format('d/m/Y à H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Informations de la séance --}}
    <table class="infos">
        <tr>
            <td><div class="lbl">Formation</div><div class="val">{{ $d['formation']->libelle ?? '—' }}</div></td>
            <td><div class="lbl">Session / Promotion</div><div class="val">{{ $d['promotion']->nom_complet ?? '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="lbl">Titre RNCP</div><div class="val">{{ $d['formation']->rncp_intitule ?? '—' }}</div></td>
            <td><div class="lbl">Code RNCP</div><div class="val">{{ $d['formation']->code_rncp ?? '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="lbl">Date</div><div class="val">{{ $d['seance']->date?->format('d/m/Y') ?? '—' }}</div></td>
            <td><div class="lbl">Horaires</div><div class="val">{{ $d['horaires'] ?? '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="lbl">Matière / Objet</div><div class="val">{{ $d['seance']->libelle ?? '—' }}</div></td>
            <td><div class="lbl">Lieu</div><div class="val">{{ $d['lieu'] ?? '—' }}</div></td>
        </tr>
        <tr>
            <td colspan="2"><div class="lbl">Formateur</div><div class="val">{{ $d['formateur']->name ?? '—' }}</div></td>
        </tr>
    </table>

    {{-- Tableau d'émargement --}}
    <table class="emarge">
        <thead>
            <tr>
                <th class="c" style="width:24px">N°</th>
                <th style="width:34%">Apprenant</th>
                <th class="c" style="width:78px">Présence<br>relevée</th>
                <th class="c">Signature de l'apprenant</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($d['lignes'] as $l)
                <tr>
                    <td class="num">{{ $l['num'] }}</td>
                    <td>
                        <div class="appr">{{ $l['apprenant'] }}</div>
                        @if ($l['commentaire'])<div class="obs">{{ $l['commentaire'] }}</div>@endif
                    </td>
                    <td class="c"><span class="statut" style="color: {{ $l['couleur'] }}">{{ $l['statut'] }}</span></td>
                    <td class="sign-cell">
                        @if ($l['signature'])
                            <img src="{{ $l['signature'] }}" style="max-height:32px;max-width:150px;display:block;margin:0 auto;">
                            @if ($l['signe_a'])<div style="text-align:center;color:#9ca3af;font-size:7.5px;margin-top:1px;">signé le {{ $l['signe_a']->format('d/m/Y H:i') }}</div>@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:22px;font-style:italic;">
                    Aucun apprenant rattaché à cette séance.
                </td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Observations + signature formateur --}}
    <table class="zone">
        <tr>
            <td style="width:58%">
                <div class="obs-box">
                    <div class="k">Observations générales</div>
                </div>
            </td>
            <td style="width:42%">
                <div class="sign-box">
                    <div class="k">Signature du formateur</div>
                    <div class="who">{{ $d['formateur']->name ?? '' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="fait">
        Fait à ______________________________ , le {{ $d['seance']->date?->format('d/m/Y') ?? '____/____/________' }}
    </div>

    <div class="foot">
        Fiche d'émargement générée par l'ERP — {{ $cfa->designation() }}
        · Réf. séance S-{{ $d['seance']->getKey() }} · {{ $d['edite_le']->format('d/m/Y à H:i') }}
    </div>
</div>
</body>
</html>