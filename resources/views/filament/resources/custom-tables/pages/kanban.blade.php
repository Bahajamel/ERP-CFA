<x-filament-panels::page>
    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach ($this->getColonnesKanban() as $colonne)
            @php
                $valeur = $colonne['valeur'];
                $lignes = $colonne['lignes'];
                $titre = $valeur === '' ? 'Sans statut' : $valeur;
            @endphp

            <div
                wire:key="kcol-{{ $loop->index }}"
                class="flex w-72 flex-shrink-0 flex-col rounded-xl bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            >
                {{-- En-tête de colonne (couleur du statut) --}}
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $colonne['couleur'] }}"></span>
                        {{ $titre }}
                    </span>
                    <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-gray-200 px-2 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ $lignes->count() }}
                    </span>
                </div>

                {{-- Cartes : glisser-déposer entre colonnes (x-sort) → change le statut --}}
                <div
                    x-sort:group="kanban"
                    x-sort="$wire.moveCard($item, @js($valeur))"
                    class="space-y-2 overflow-y-auto p-2"
                    style="height: calc(100vh - 20rem); min-height: 12rem;"
                >
                    @forelse ($lignes as $ligne)
                        <div
                            x-sort:item="{{ $ligne->getKey() }}"
                            wire:key="krow-{{ $ligne->getKey() }}"
                            class="cursor-grab rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md active:cursor-grabbing dark:bg-gray-800 dark:ring-white/10"
                        >
                            <div class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                                @foreach ($this->colonnesCarte() as $col)
                                    @php $v = data_get($ligne->data, $col->key); @endphp
                                    @if (filled($v) || $col->type->value === 'boolean')
                                        <div class="truncate">
                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $col->label }} :</span>
                                            @if ($col->type->value === 'boolean')
                                                {{ $v ? 'Oui' : 'Non' }}
                                            @else
                                                {{ $v }}
                                            @endif
                                        </div>
                                    @endif
                                @endforeach

                                @if ($this->colonnesCarte()->isEmpty())
                                    <div class="text-gray-400">Ligne #{{ $ligne->getKey() }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full min-h-[8rem] items-center justify-center rounded-lg border-2 border-dashed border-gray-200 text-center text-xs text-gray-400 dark:border-white/10">
                            Déposez une ligne ici
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
