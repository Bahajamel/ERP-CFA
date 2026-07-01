<x-filament-panels::page>
    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach ($this->getColumns() as $column)
            @php
                $statut = $column['statut'];
                $candidates = $column['candidates'];
            @endphp

            <div
                wire:key="col-{{ $statut->value }}"
                class="flex w-72 flex-shrink-0 flex-col rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            >
                {{-- En-tête de colonne --}}
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                    <x-filament::badge :color="$statut->getColor()" :icon="$statut->getIcon()">
                        {{ $statut->getLabel() }}
                    </x-filament::badge>

                    <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-gray-200 px-2 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ $candidates->count() }}
                    </span>
                </div>

                {{-- Zone de dépôt : hauteur fixe + défilement interne (drag & drop x-sort) --}}
                <div
                    x-sort:group="candidates"
                    x-sort="$wire.moveCard($item, @js($statut->value))"
                    class="space-y-2 overflow-y-auto p-2"
                    style="height: calc(100vh - 21rem); min-height: 12rem;"
                >
                    @forelse ($candidates as $candidate)
                        <div
                            x-sort:item="{{ $candidate->id }}"
                            wire:key="cand-{{ $candidate->id }}"
                            class="cursor-grab rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md active:cursor-grabbing dark:bg-gray-800 dark:ring-white/10"
                        >
                            <a
                                href="{{ \App\Filament\Resources\Candidates\CandidateResource::getUrl('edit', ['record' => $candidate]) }}"
                                class="font-medium text-gray-950 hover:underline dark:text-white"
                            >
                                {{ $candidate->nom_complet }}
                            </a>

                            <div class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                @if ($candidate->formationVisee)
                                    <div class="truncate">{{ $candidate->formationVisee->libelle }}</div>
                                @endif
                                @if ($candidate->commercial)
                                    <div>{{ $candidate->commercial->name }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full min-h-[8rem] items-center justify-center rounded-lg border-2 border-dashed border-gray-200 text-center text-xs text-gray-400 dark:border-white/10">
                            Déposez un candidat ici
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
