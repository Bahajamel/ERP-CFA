@php
    /** @var \App\Models\Invoice $invoice */
    $line = $invoice->financeLine;
    $contrat = $line?->contract;
    $brouillon = $invoice->statut === \App\Enums\InvoiceStatut::Brouillon;
    $euros = fn ($m) => number_format((float) $m, 2, ',', ' ').' €';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .sub { color: #555; font-size: 11px; margin-bottom: 20px; }
        .watermark { color: #d0d0d0; font-size: 40px; font-weight: bold; text-align: center; margin: 10px 0 18px; letter-spacing: 4px; }
        table.block { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.block th { background: #f0f0f0; text-align: left; padding: 7px 9px; border: 1px solid #ccc; }
        table.block td { border: 1px solid #ccc; padding: 7px 9px; }
        td.lbl { width: 45%; color: #555; }
        .big { font-size: 16px; font-weight: bold; }
        .right { text-align: right; }
        .foot { margin-top: 26px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>Facture {{ $invoice->numero ?: '(brouillon)' }}</h1>
    <div class="sub">
        Émise le {{ optional($invoice->date_emission)->format('d/m/Y') ?: '—' }}
        · Échéance {{ optional($invoice->date_echeance)->format('d/m/Y') ?: '—' }}
    </div>

    @if ($brouillon)
        <div class="watermark">BROUILLON</div>
    @endif

    <table class="block">
        <tr><th colspan="2">Destinataire</th></tr>
        <tr><td class="lbl">Client facturé</td><td>{{ $invoice->destinataire ?: '—' }}</td></tr>
        <tr><td class="lbl">Entreprise (contrat)</td><td>{{ $contrat?->company?->raison_sociale ?? '—' }}</td></tr>
        <tr><td class="lbl">Apprenti concerné</td><td>{{ $contrat?->candidate?->nom_complet ?? ($contrat?->candidate?->nom ?? '—') }}</td></tr>
    </table>

    <table class="block">
        <tr><th>Désignation</th><th class="right">Montant</th></tr>
        <tr>
            <td>{{ $line?->libelle ?? 'Prestation de formation' }}</td>
            <td class="right big">{{ $euros($invoice->montant) }}</td>
        </tr>
        <tr>
            <td class="right lbl">Total à payer</td>
            <td class="right big">{{ $euros($invoice->montant) }}</td>
        </tr>
    </table>

    <div class="foot">
        Document généré automatiquement par l'ERP CFA.
        @if ($brouillon)
            Ce brouillon n'a pas de valeur comptable tant que la facture n'est pas émise.
        @else
            Facture émise — {{ $invoice->statut->getLabel() }}.
        @endif
    </div>
</body>
</html>
