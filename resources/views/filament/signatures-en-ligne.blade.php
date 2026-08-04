<div class="space-y-5">

    {{-- LE QR unique de séance : à projeter / partager --}}
    <div class="flex flex-col items-center gap-3 rounded-xl border border-gray-200 p-5 text-center dark:border-white/10">
        <img src="{{ $qrSeance }}" alt="QR de signature de la séance" width="200" height="200"
             class="rounded-lg bg-white p-2 ring-1 ring-gray-200 dark:ring-white/10">
        <p class="text-sm font-semibold text-gray-900 dark:text-white">Scannez ce QR pour signer</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Un seul QR pour toute la classe — projetez-le au tableau.</p>
        <div class="flex w-full max-w-md items-center gap-2">
            <input type="text" readonly value="{{ $lienSeance }}"
                   class="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                   onclick="this.select()">
            <button type="button"
                    class="shrink-0 rounded-md bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-500"
                    onclick="navigator.clipboard.writeText('{{ $lienSeance }}'); this.textContent='Copié'; setTimeout(() => this.textContent='Copier le lien', 1500);">
                Copier le lien
            </button>
        </div>
    </div>

    {{-- Suivi en direct : qui a signé --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Suivi des signatures</p>
        <div class="space-y-1.5">
            @forelse ($lignes as $l)
                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                    <span class="truncate text-sm text-gray-900 dark:text-white">{{ $l['nom'] }}</span>
                    @if ($l['signe'])
                        <span class="shrink-0 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            ✓ Signé{{ $l['signed_at'] ? ' à '.$l['signed_at']->format('H\hi') : '' }}
                        </span>
                    @else
                        <span class="shrink-0 text-xs text-amber-600 dark:text-amber-400">En attente</span>
                    @endif
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-gray-200 p-6 text-center text-sm text-gray-400 dark:border-white/10">
                    Aucun apprenant rattaché à cette séance.
                </p>
            @endforelse
        </div>
    </div>
</div>