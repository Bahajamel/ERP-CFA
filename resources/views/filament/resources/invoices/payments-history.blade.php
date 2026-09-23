{{-- Historique des encaissements d'une facture (Phase B). Lecture seule. --}}
<div class="fi-modal-content flex flex-col gap-4">
    <div class="grid grid-cols-3 gap-3 text-sm">
        <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3">
            <div class="text-gray-500 dark:text-gray-400">Montant facture</div>
            <div class="font-semibold text-gray-950 dark:text-white">
                {{ number_format((float) $invoice->montant, 2, ',', ' ') }} €
            </div>
        </div>
        <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3">
            <div class="text-gray-500 dark:text-gray-400">Déjà encaissé</div>
            <div class="font-semibold text-success-600 dark:text-success-400">
                {{ number_format($invoice->montantPaye(), 2, ',', ' ') }} €
            </div>
        </div>
        <div class="rounded-lg bg-gray-50 dark:bg-white/5 p-3">
            <div class="text-gray-500 dark:text-gray-400">Reste à payer</div>
            <div @class([
                'font-semibold',
                'text-warning-600 dark:text-warning-400' => $invoice->resteAPayer() > 0,
                'text-success-600 dark:text-success-400' => $invoice->resteAPayer() <= 0,
            ])>
                {{ number_format($invoice->resteAPayer(), 2, ',', ' ') }} €
            </div>
        </div>
    </div>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                <th class="py-2 pr-3 font-medium">Date</th>
                <th class="py-2 pr-3 font-medium">Montant</th>
                <th class="py-2 pr-3 font-medium">Moyen</th>
                <th class="py-2 font-medium">Référence</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse ($invoice->payments as $payment)
                <tr class="text-gray-950 dark:text-white">
                    <td class="py-2 pr-3">{{ $payment->date_paiement?->format('d/m/Y') }}</td>
                    <td class="py-2 pr-3 font-medium">{{ number_format((float) $payment->montant, 2, ',', ' ') }} €</td>
                    <td class="py-2 pr-3">{{ $payment->moyen ?? '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $payment->reference ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">
                        Aucun encaissement enregistré.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>