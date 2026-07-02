<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 17px; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; color: #555; font-size: 11px; margin-bottom: 22px; }
        table.block { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.block th { background: #f0f0f0; text-align: left; padding: 7px 9px; border: 1px solid #ccc; }
        table.block td { border: 1px solid #ccc; padding: 7px 9px; }
        td.lbl { width: 45%; color: #555; }
        .big { font-size: 15px; font-weight: bold; }
        .foot { margin-top: 26px; font-size: 10px; color: #666; }
        .sign { margin-top: 40px; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Attestation de service fait</h1>
    <div class="sub">Assiduité mensuelle — justificatif de la prestation de formation réalisée</div>

    <table class="block">
        <tr><th colspan="2">Période &amp; classe</th></tr>
        <tr><td class="lbl">Classe / Promotion</td><td>{{ $sf->promotion?->libelle ?? '—' }}</td></tr>
        <tr><td class="lbl">Formation</td><td>{{ $sf->promotion?->formation?->libelle ?? '—' }}</td></tr>
        <tr><td class="lbl">Période</td><td class="big">{{ $sf->periodeLibelle() }}</td></tr>
    </table>

    <table class="block">
        <tr><th colspan="2">Prestation réalisée</th></tr>
        <tr><td class="lbl">Nombre de séances</td><td>{{ $sf->nb_seances }}</td></tr>
        <tr><td class="lbl">Volume horaire réalisé</td><td>{{ rtrim(rtrim(number_format((float) $sf->nb_heures, 1, ',', ' '), '0'), ',') }} heures</td></tr>
        <tr>
            <td class="lbl">Taux d'assiduité</td>
            <td class="big">{{ $sf->taux_presence === null ? '—' : $sf->taux_presence.' %' }}</td>
        </tr>
    </table>

    <div class="sign">
        Attestation générée le {{ optional($sf->validated_at)->format('d/m/Y') }}
        @if ($sf->validatedBy) par {{ $sf->validatedBy->name }} @endif.
        Le service fait ci-dessus atteste que les séances de formation ont été assurées
        et l'assiduité enregistrée pour la période indiquée.
    </div>

    <div class="foot">
        Document généré automatiquement par l'ERP CFA. Sert de justificatif du service fait
        (base de la facturation OPCO et des preuves qualité).
    </div>
</body>
</html>
