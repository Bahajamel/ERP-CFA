<div class="space-y-3">
    @forelse ($cibles as $cible)
        <div class="flex items-start justify-between gap-3 rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="space-y-1">
                <a
                    href="{{ \App\Filament\Resources\Companies\CompanyResource::getUrl('view', ['record' => $cible['company']]) }}"
                    class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    {{ $cible['company']->raison_sociale }}
                </a>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cible['raison'] }}</p>
            </div>

            @if ($cible['score'] > 0)
                <x-filament::badge color="success">{{ $cible['score'] }} pts</x-filament::badge>
            @else
                <x-filament::badge color="gray">Partenaire</x-filament::badge>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Aucune entreprise à cibler pour l'instant : aucun besoin ouvert compatible et aucun
            partenaire ayant déjà recruté dans la formation visée.
        </p>
    @endforelse
</div>
