@php
    $euros = fn ($m) => number_format((float) $m, 0, ',', ' ').' €';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 11px; margin-bottom: 18px; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
        table.kpi { width: 100%; border-collapse: collapse; }
        table.kpi td { padding: 5px 8px; border: 1px solid #e5e7eb; }
        td.lbl { color: #555; width: 60%; }
        td.val { text-align: right; font-weight: bold; }
        .foot { margin-top: 26px; font-size: 10px; color: #888; }
        .big { font-size: 14px; }
    </style>
</head>
<body>
    <h1>Rapport de pilotage — CFA</h1>
    <div class="sub">Synthèse générée le {{ $r['genere_le']->format('d/m/Y à H:i') }}</div>

    <h2>Candidats &amp; admissions</h2>
    <table class="kpi">
        <tr><td class="lbl">Candidats actifs (hors ruptures)</td><td class="val">{{ $r['candidats']['actifs'] }}</td></tr>
        <tr><td class="lbl">Candidats à placer (en recherche d'entreprise)</td><td class="val">{{ $r['candidats']['a_placer'] }}</td></tr>
        <tr><td class="lbl">Admissions validées</td><td class="val">{{ $r['admissions']['validees'] }}</td></tr>
        <tr><td class="lbl">Admissions en cours</td><td class="val">{{ $r['admissions']['en_cours'] }}</td></tr>
    </table>

    <h2>Contrats</h2>
    <table class="kpi">
        <tr><td class="lbl">Contrats signés</td><td class="val">{{ $r['contrats']['signes'] }}</td></tr>
        <tr><td class="lbl">Contrats actifs</td><td class="val">{{ $r['contrats']['actifs'] }}</td></tr>
        <tr><td class="lbl">Contrats rompus</td><td class="val">{{ $r['contrats']['rompus'] }}</td></tr>
        <tr><td class="lbl">Contrats à risque de rupture (élevé/critique)</td><td class="val">{{ $r['contrats']['a_risque'] }}</td></tr>
    </table>

    <h2>Financement OPCO</h2>
    <table class="kpi">
        <tr><td class="lbl">Dossiers OPCO en cours</td><td class="val">{{ $r['opco']['en_cours'] }}</td></tr>
        <tr><td class="lbl">Dossiers OPCO bloqués (rejet / correction)</td><td class="val">{{ $r['opco']['bloques'] }}</td></tr>
        <tr><td class="lbl">Montant OPCO accepté (cumulé)</td><td class="val">{{ $euros($r['opco']['montant_accepte']) }}</td></tr>
    </table>

    <h2>Finance &amp; recouvrement</h2>
    <table class="kpi">
        <tr><td class="lbl">Total facturé</td><td class="val">{{ $euros($r['finance']['facture']) }}</td></tr>
        <tr><td class="lbl">Total encaissé</td><td class="val">{{ $euros($r['finance']['encaisse']) }}</td></tr>
        <tr><td class="lbl">Taux de recouvrement</td><td class="val big">{{ $r['finance']['taux'] }} %</td></tr>
        <tr><td class="lbl">Impayés échus (restant dû)</td><td class="val">{{ $euros($r['finance']['impayes']) }}</td></tr>
    </table>

    <h2>Assiduité &amp; alertes</h2>
    <table class="kpi">
        <tr><td class="lbl">Taux de présence moyen</td><td class="val">{{ $r['assiduite'] === null ? '—' : $r['assiduite'].' %' }}</td></tr>
        <tr><td class="lbl">Tâches / alertes en retard</td><td class="val">{{ $r['alertes'] }}</td></tr>
    </table>

    <div class="foot">
        Document généré automatiquement par l'ERP CFA — usage interne de pilotage.
        Les montants finance sont indicatifs ; la comptabilité fait foi.
    </div>
</body>
</html>
