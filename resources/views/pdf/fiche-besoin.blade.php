<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche besoin — {{ $d['entreprise'] ?? 'entreprise' }}</title>
    <style>
        /* dompdf : pas de flexbox, tout en tables. DejaVu Sans pour les accents.
           Document narratif A4 — sans logo ni bas de page, à la demande du CFA. */
        @page { margin: 34px 46px 34px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 11px; line-height: 1.5; }

        /* En-tête : identification du CFA à droite (pas de logo), filet bleu. */
        .head { border-bottom: 2px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { vertical-align: bottom; }
        .head .cfa { text-align: right; }
        .head .cfa .t { font-size: 13px; font-weight: bold; color: #14142b; }
        .head .cfa .s { font-size: 10px; color: #6b7280; font-style: italic; }

        /* Titre encadré, centré, fond bleu très clair. */
        .titre {
            border: 1px solid #93b4f5; background: #eef4ff; border-radius: 3px;
            text-align: center; font-size: 15px; font-weight: bold; color: #14142b;
            letter-spacing: .3px; padding: 8px 10px; margin-bottom: 6px;
        }
        .intro { font-size: 10px; color: #6b7280; font-style: italic; margin-bottom: 16px; }

        /* Titres de section bleus. */
        h2 {
            font-size: 12.5px; font-weight: bold; color: #1d4ed8;
            margin: 16px 0 7px 0;
        }

        /* Table « Informations générales » : libellés gras à gauche, valeurs à droite. */
        table.infos { width: 100%; border-collapse: collapse; }
        table.infos td { border: 1px solid #d5dbe6; padding: 5px 9px; vertical-align: top; }
        table.infos td.k { width: 195px; font-weight: bold; background: #f8fafc; color: #14142b; }
        .vide { color: #9ca3af; font-style: italic; }

        p.txt { margin: 0 0 8px 0; text-align: justify; }

        ul.comp { margin: 4px 0 8px 0; padding-left: 18px; }
        ul.comp li { margin-bottom: 3px; }

        .accent { font-weight: bold; }
    </style>
</head>
<body>

{{-- ─────────── En-tête (sans logo) ─────────── --}}
<table class="head">
    <tr>
        <td class="cfa">
            <div class="t">Fiche besoin</div>
            @if ($d['cfa_nom'])<div class="s">{{ $d['cfa_nom'] }}</div>@endif
        </td>
    </tr>
</table>

<div class="titre">FICHE BESOIN — ALTERNANCE / APPRENTISSAGE</div>
<div class="intro">{{ $d['sous_titre'] }}</div>

{{-- ─────────── 1 · Informations générales ─────────── --}}
<h2>1. Informations générales</h2>
<table class="infos">
    <tr>
        <td class="k">Entreprise d'accueil</td>
        <td>
            @if ($d['entreprise'])
                {{ $d['entreprise'] }}@if ($d['entreprise_siret']) — SIRET {{ $d['entreprise_siret'] }}@endif
            @else
                <span class="vide">à compléter</span>
            @endif
        </td>
    </tr>
    <tr>
        <td class="k">Formation visée</td>
        <td>{{ $d['formation'] ?? '' }}@unless ($d['formation'])<span class="vide">à compléter</span>@endunless</td>
    </tr>
    <tr>
        <td class="k">Code RNCP</td>
        <td>{{ $d['code_rncp'] ?? '' }}@unless ($d['code_rncp'])<span class="vide">à compléter</span>@endunless</td>
    </tr>
    <tr>
        <td class="k">Poste envisagé</td>
        <td>{{ $d['poste'] ?? '' }}@unless ($d['poste'])<span class="vide">à compléter</span>@endunless</td>
    </tr>
    <tr>
        <td class="k">Maître d'apprentissage</td>
        <td>{{ $d['maitre_apprentissage'] ?? '' }}@unless ($d['maitre_apprentissage'])<span class="vide">à désigner</span>@endunless</td>
    </tr>
    <tr>
        <td class="k">Date de la fiche besoin</td>
        <td>{{ $d['date_fiche'] }}</td>
    </tr>
</table>

{{-- ─────────── 2 · Contexte de l'entreprise ─────────── --}}
<h2>2. Contexte de l'entreprise</h2>
<p class="txt">{{ $d['contexte'] }}</p>

{{-- ─────────── 3 · Besoin opérationnel ─────────── --}}
<h2>3. Besoin opérationnel</h2>
<p class="txt">{{ $d['besoin'] }}</p>

{{-- ─────────── 4 · Compétences attendues ─────────── --}}
<h2>4. Compétences attendues</h2>
<ul class="comp">
    @foreach ($d['competences'] as $competence)
        <li>{{ $competence }}</li>
    @endforeach
</ul>

{{-- ─────────── 5 · Justification du choix de la formation ─────────── --}}
<h2>5. Justification du choix de la formation</h2>
<p class="txt">{{ $d['justification'] }}</p>

{{-- ─────────── 6 · Conclusion ─────────── --}}
<h2>6. Conclusion</h2>
<p class="txt">{{ $d['conclusion'] }}</p>

</body>
</html>