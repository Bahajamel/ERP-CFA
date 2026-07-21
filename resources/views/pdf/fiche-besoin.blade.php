@php
    /**
     * Fiche besoin — analyse du besoin exprimé par une entreprise.
     * Sert de preuve à l'indicateur Qualiopi n°4.
     *
     * $v : valeur renseignée, ou pointillés à compléter à la main. On n'invente
     * jamais une donnée absente (même idiome que la convention de formation).
     */
    $v = fn ($val) => filled($val)
        ? '<span class="val">'.e($val).'</span>'
        : '<span class="fill"></span>';

    $case = fn (bool $coche = false) => '<span class="case">'.($coche ? '&#10003;' : '&nbsp;').'</span>';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche besoin {{ $d['reference'] }}</title>
    <style>
        /* dompdf : pas de flexbox, tout en tables. DejaVu Sans pour les accents. */
        /* Tout est calibré pour tenir sur UNE page A4 : une fiche besoin qui
           déborde est inutilisable en réunion ou en audit. Un test vérifie
           qu'elle reste sur une seule page. */
        @page { margin: 20px 30px 20px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 10px; line-height: 1.3; }

        .head { border-bottom: 3px solid #4f46e5; padding-bottom: 7px; margin-bottom: 10px; }
        .head td { vertical-align: top; }
        .cfa-nom { font-size: 15px; font-weight: bold; color: #312e81; }
        .cfa-meta { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .t { font-size: 19px; font-weight: bold; color: #4f46e5; letter-spacing: .5px; }
        .doc-title .r { font-size: 11px; font-weight: bold; color: #312e81; margin-top: 3px; }
        .doc-title .dt { font-size: 9px; color: #6b7280; }

        .sec-h {
            background: #eef2ff; color: #312e81; font-weight: bold; font-size: 10px;
            padding: 3px 7px; margin: 9px 0 4px 0; border-left: 3px solid #4f46e5;
        }
        .sec-h .n { color: #4f46e5; }

        table.f { width: 100%; border-collapse: collapse; }
        table.f td { padding: 1.5px 6px 1.5px 0; vertical-align: top; }
        td.lbl { color: #6b7280; width: 122px; font-size: 9px; }
        .val { font-weight: bold; color: #14142b; }
        .fill { display: inline-block; min-width: 130px; border-bottom: 1px dotted #9aa2b1; height: 10px; }

        .bloc { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 9px; }
        .bloc .txt { white-space: pre-line; }
        .vide { color: #9ca3af; font-style: italic; }

        .case {
            display: inline-block; width: 11px; height: 11px; border: 1px solid #6b7280;
            margin-right: 4px; text-align: center; line-height: 11px; font-size: 9px;
        }
        .choix { margin-right: 18px; }

        .cadre { border: 1px solid #9aa2b1; border-radius: 3px; height: 30px; margin-top: 2px; }
        .cadre-h { height: 40px; }

        .qualiopi {
            border: 1px solid #c7d2fe; border-radius: 4px; padding: 7px 9px; background: #fafaff;
        }
        .qualiopi .ref { font-size: 8px; color: #6366f1; font-style: italic; margin-bottom: 4px; }

        .reserve { border: 1px dashed #9aa2b1; border-radius: 4px; padding: 7px 9px; background: #fcfcfd; }

        .badge {
            display: inline-block; padding: 1px 6px; border-radius: 8px;
            font-size: 9px; font-weight: bold;
        }
        .badge-attente { background: #ede9fe; color: #6d28d9; }
        .badge-ok { background: #dcfce7; color: #15803d; }

        /* Bloc normal en fin de flux (comme le bulletin) plutôt que
           position:fixed, dont le rendu dompdf est capricieux. */
        .foot {
            margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 5px;
            font-size: 8px; color: #9ca3af; text-align: center;
        }
    </style>
</head>
<body>

{{-- ─────────── En-tête ─────────── --}}
<table class="head">
    <tr>
        <td>
            <div class="cfa-nom">{{ $d['cfa_designation'] ?? $d['cfa_nom'] ?? 'CFA' }}</div>
            <div class="cfa-meta">
                @if ($d['cfa_adresse']){{ $d['cfa_adresse'] }}@endif
                @if ($d['cfa_siret']) · SIRET {{ $d['cfa_siret'] }}@endif
                @if ($d['cfa_nda']) · NDA {{ $d['cfa_nda'] }}@endif
            </div>
        </td>
        <td class="doc-title">
            <div class="t">FICHE BESOIN</div>
            <div class="r">N° {{ $d['reference'] }}</div>
            <div class="dt">Éditée le {{ $d['edite_le'] }}</div>
        </td>
    </tr>
</table>

{{-- ─────────── 1 · Entreprise ─────────── --}}
<div class="sec-h"><span class="n">1</span> &nbsp;ENTREPRISE</div>
<table class="f">
    <tr>
        <td class="lbl">Raison sociale</td><td>{!! $v($d['entreprise']) !!}</td>
        <td class="lbl">SIRET</td><td>{!! $v($d['entreprise_siret']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Secteur d'activité</td><td colspan="3">{!! $v($d['entreprise_secteur']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Adresse</td><td colspan="3">{!! $v($d['entreprise_adresse']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">OPCO</td><td colspan="3">{!! $v($d['entreprise_opco']) !!}</td>
    </tr>
</table>

{{-- ─────────── 2 · Interlocuteur ─────────── --}}
<div class="sec-h"><span class="n">2</span> &nbsp;INTERLOCUTEUR</div>
<table class="f">
    <tr>
        <td class="lbl">Contact</td><td colspan="3">{!! $v($d['contact_identite']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Adresse e-mail</td><td>{!! $v($d['contact_email']) !!}</td>
        <td class="lbl">Téléphone</td><td>{!! $v($d['contact_tel']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Tuteur pressenti</td><td colspan="3">{!! $v($d['tuteur_identite']) !!}</td>
    </tr>
</table>

{{-- ─────────── 3 · Poste recherché ─────────── --}}
<div class="sec-h"><span class="n">3</span> &nbsp;POSTE RECHERCHÉ</div>
<table class="f">
    <tr>
        <td class="lbl">Intitulé du poste</td><td colspan="3">{!! $v($d['poste']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Nombre de postes</td><td>{!! $v($d['nb_postes']) !!}</td>
        <td class="lbl">Date de démarrage</td><td>{!! $v($d['date_demarrage']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Formation visée</td><td>{!! $v($d['formation']) !!}</td>
        <td class="lbl">Rythme d'alternance</td><td>{!! $v($d['rythme']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Lieu de la mission</td><td colspan="3">{!! $v($d['lieu']) !!}</td>
    </tr>
</table>

{{-- ─────────── 4 · Missions et prérequis ─────────── --}}
<div class="sec-h"><span class="n">4</span> &nbsp;MISSIONS CONFIÉES ET PRÉREQUIS</div>
<div class="bloc">
    @if (filled($d['prerequis']))
        <div class="txt">{{ $d['prerequis'] }}</div>
    @else
        <div class="vide">Non renseigné par l'entreprise — à recueillir lors de la qualification.</div>
        <div class="cadre cadre-h"></div>
    @endif
</div>

{{-- ─────────── 5 · Analyse du besoin (Qualiopi n°4) ─────────── --}}
<div class="sec-h"><span class="n">5</span> &nbsp;ANALYSE DU BESOIN</div>
<div class="qualiopi">
    <div class="ref">
        Référentiel National Qualité — indicateur n°4 : « Le prestataire analyse le besoin du
        bénéficiaire en lien avec l'entreprise et/ou le(s) financeur(s) concerné(s). »
    </div>

    <table class="f">
        <tr>
            <td class="lbl">Certification visée</td><td colspan="3">{!! $v($d['certification']) !!}</td>
        </tr>
        <tr>
            <td class="lbl">Code RNCP</td><td>{!! $v($d['certification_rncp']) !!}</td>
            <td class="lbl">Niveau</td><td>{!! $v($d['certification_niveau']) !!}</td>
        </tr>
    </table>

    <div style="margin-top:8px;">
        <b style="font-size:10px;">Adéquation des missions confiées à la certification visée</b><br>
        <span class="choix">{!! $case() !!}Conforme</span>
        <span class="choix">{!! $case() !!}Conforme avec réserves</span>
        <span class="choix">{!! $case() !!}Non conforme</span>
    </div>

    <div style="margin-top:6px;">
        <span style="font-size:10px; color:#6b7280;">Motivation de l'analyse</span>
        <div class="cadre"></div>
    </div>

    <table class="f" style="margin-top:8px;">
        <tr>
            <td class="lbl">Analyse réalisée par</td><td>{!! $v(null) !!}</td>
            <td class="lbl">Le</td><td>{!! $v(null) !!}</td>
        </tr>
    </table>

    <div style="margin-top:4px;">
        {{-- Les deux moments admis par l'indicateur 4 pour l'apprentissage. --}}
        <span class="choix">{!! $case() !!}Analyse menée en amont de la contractualisation</span><br>
        <span class="choix">{!! $case() !!}Analyse complétée en début de parcours</span>
    </div>
</div>

{{-- ─────────── 6 · Suivi CFA ─────────── --}}
<div class="sec-h"><span class="n">6</span> &nbsp;SUIVI CFA</div>
<table class="f">
    <tr>
        <td class="lbl">Origine</td>
        <td>
            {!! $v($d['origine']) !!}
            @if ($d['depose_le']) <span style="color:#6b7280;">le {{ $d['depose_le'] }}</span>@endif
        </td>
        <td class="lbl">Candidats proposés</td><td>{!! $v($d['nb_candidats']) !!}</td>
    </tr>
    <tr>
        <td class="lbl">Statut de l'offre</td>
        <td>
            {!! $v($d['statut']) !!}
            @if ($d['attend_validation'])
                <span class="badge badge-attente">en attente de validation</span>
            @elseif ($d['validee_le'])
                <span class="badge badge-ok">validée le {{ $d['validee_le'] }}</span>
            @endif
        </td>
        <td class="lbl">Validée le</td><td>{!! $v($d['validee_le']) !!}</td>
    </tr>
</table>

{{-- ─────────── Cadre réservé ─────────── --}}
<div class="sec-h">CADRE RÉSERVÉ AU CFA</div>
<div class="reserve">
    <table class="f">
        <tr>
            <td class="lbl">Qualifié par</td><td>{!! $v(null) !!}</td>
            <td class="lbl">Le</td><td>{!! $v(null) !!}</td>
        </tr>
    </table>
    <span style="font-size:10px; color:#6b7280;">Observations et suites données</span>
    <div class="cadre cadre-h"></div>
</div>

<div class="foot">
    Fiche besoin {{ $d['reference'] }} — document généré par l'ERP du CFA
    @if ($d['cfa_nom']) · {{ $d['cfa_nom'] }} @endif
    · {{ $d['edite_le'] }}
</div>

</body>
</html>
