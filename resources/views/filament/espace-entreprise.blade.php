<div class="space-y-4">
    <div class="flex flex-col items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
        <img src="{{ $qr }}" alt="QR de l'espace entreprise" class="h-48 w-48 rounded-lg bg-white p-2">
        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
            L'entreprise scanne ce QR pour ouvrir son espace, ou utilise le lien ci-dessous.
        </p>
    </div>

    <div>
        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Lien personnel</label>
        <input type="text" readonly value="{{ $lien }}"
               onclick="this.select();document.execCommand&&document.execCommand('copy')"
               class="w-full cursor-pointer rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
        <p class="mt-1 text-xs text-gray-400">Cliquez pour sélectionner / copier. Ce lien est personnel : à ne pas partager publiquement.</p>
    </div>

    @if (filled($email))
        <p class="rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
            « Envoyer le lien par e-mail » l'expédiera au contact <strong>{{ $email }}</strong>.
        </p>
    @else
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
            Aucun contact avec e-mail : partagez le lien ou le QR manuellement.
        </p>
    @endif
</div>