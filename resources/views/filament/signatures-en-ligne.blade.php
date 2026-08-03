<div class="space-y-3">
    @forelse ($lignes as $l)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $l['nom'] }}</p>
                @if ($l['signe'])
                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        ✓ Signé{{ $l['signed_at'] ? ' le '.$l['signed_at']->format('d/m/Y à H:i') : '' }}
                    </p>
                @else
                    <p class="text-xs text-amber-600 dark:text-amber-400">En attente — l'apprenant scanne le QR ou ouvre le lien</p>
                @endif
            </div>

            @unless ($l['signe'])
                <div class="flex shrink-0 items-center gap-3">
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $l['lien'] }}"
                               class="w-48 rounded-md border-gray-300 bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                               onclick="this.select()">
                        <button type="button"
                                class="rounded-md bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-500"
                                onclick="navigator.clipboard.writeText('{{ $l['lien'] }}'); this.textContent='Copié'; setTimeout(() => this.textContent='Copier', 1500);">
                            Copier
                        </button>
                    </div>
                    <img src="{{ $l['qr'] }}" alt="QR signature" width="72" height="72"
                         class="shrink-0 rounded bg-white p-1 ring-1 ring-gray-200 dark:ring-white/10">
                </div>
            @endunless
        </div>
    @empty
        <p class="rounded-lg border border-dashed border-gray-200 p-6 text-center text-sm text-gray-400 dark:border-white/10">
            Aucun apprenant rattaché à cette séance.
        </p>
    @endforelse
</div>