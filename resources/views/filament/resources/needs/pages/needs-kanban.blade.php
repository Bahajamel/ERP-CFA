<x-filament-panels::page>
    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach ($this->getColumns() as $column)
            @php
                $statut = $column['statut'];
                $needs = $column['needs'];
            @endphp

            <div
                wire:key="col-{{ $statut->value }}"
                class="flex w-72 flex-shrink-0 flex-col rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            >
                {{-- En-tête de colonne --}}
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                    <x-filament::badge :color="$statut->getColor()">
                        {{ $statut->getLabel() }}
                    </x-filament::badge>

                    <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-gray-200 px-2 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ $needs->count() }}
                    </span>
                </div>

                {{-- Zone de dépôt : hauteur fixe + défilement interne (drag & drop x-sort) --}}
                <div
                    x-sort:group="needs"
                    x-sort="$wire.moveCard($item, @js($statut->value))"
                    class="space-y-2 overflow-y-auto p-2"
                    style="height: calc(100vh - 21rem); min-height: 12rem;"
                >
                    @forelse ($needs as $need)
                        <div
                            x-sort:item="{{ $need->id }}"
                            wire:key="need-{{ $need->id }}"
                            class="cursor-grab rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md active:cursor-grabbing dark:bg-gray-800 dark:ring-white/10"
                        >
                            <a
                                href="{{ \App\Filament\Resources\Needs\NeedResource::getUrl('edit', ['record' => $need]) }}"
                                class="font-medium text-gray-950 hover:underline dark:text-white"
                            >
                                {{ $need->intitule_poste }}
                            </a>

                            <div class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                @if ($need->company)
                                    <div class="truncate">{{ $need->company->raison_sociale }}</div>
                                @endif
                                @if ($need->formation)
                                    <div class="truncate">{{ $need->formation->libelle }}</div>
                                @endif
                            </div>

                            <div class="mt-2 flex items-center gap-2 text-xs">
                                <x-filament::badge color="info" size="sm">
                                    {{ $need->matchings_count }} candidat(s)
                                </x-filament::badge>
                                @if ($need->nb_postes)
                                    <span class="text-gray-400">· {{ $need->nb_postes }} poste(s)</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full min-h-[8rem] items-center justify-center rounded-lg border-2 border-dashed border-gray-200 text-center text-xs text-gray-400 dark:border-white/10">
                            Aucun besoin ici
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
