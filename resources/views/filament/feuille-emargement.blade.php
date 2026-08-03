<div class="space-y-5">

    {{-- Comment ça marche : 3 étapes simples --}}
    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-lg border border-gray-200 p-3 text-center dark:border-white/10">
            <div class="text-xl">📄</div>
            <p class="mt-1 text-xs font-semibold text-gray-900 dark:text-white">1. Générer</p>
            <p class="text-[11px] leading-tight text-gray-500 dark:text-gray-400">Téléchargez la fiche (PDF)</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 text-center dark:border-white/10">
            <div class="text-xl">🖊️</div>
            <p class="mt-1 text-xs font-semibold text-gray-900 dark:text-white">2. Faire signer</p>
            <p class="text-[11px] leading-tight text-gray-500 dark:text-gray-400">Imprimez et signez en séance</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-3 text-center dark:border-white/10">
            <div class="text-xl">📎</div>
            <p class="mt-1 text-xs font-semibold text-gray-900 dark:text-white">3. Déposer</p>
            <p class="text-[11px] leading-tight text-gray-500 dark:text-gray-400">Ajoutez le scan signé ci-dessous</p>
        </div>
    </div>

    {{-- Feuilles déjà déposées (versions) --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Feuilles déposées</p>
        @if ($feuilles->isEmpty())
            <p class="rounded-lg border border-dashed border-gray-200 p-4 text-center text-sm text-gray-400 dark:border-white/10">
                Aucune feuille signée déposée pour l'instant.
            </p>
        @else
            <div class="space-y-1.5">
                @foreach ($feuilles as $feuille)
                    <a href="{{ \App\Support\SecureMedia::pour($feuille, 'fichier') }}" target="_blank" rel="noopener"
                       class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:border-primary-300 dark:border-white/10">
                        <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">v{{ $feuille->version }}</span>
                        <span class="min-w-0 flex-1 truncate text-gray-900 dark:text-white">📄 {{ $feuille->nom_fichier }}</span>
                        <span class="shrink-0 text-xs text-gray-400">
                            {{ $feuille->created_at->format('d/m/Y H:i') }}@if ($feuille->uploadedBy) · {{ $feuille->uploadedBy->name }}@endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>