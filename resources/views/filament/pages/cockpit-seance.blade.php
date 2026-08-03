<x-filament-panels::page>
    @php
        $s = $this->seance();
        $stats = $this->stats();
        $lignes = $this->lignes();
        $statuts = $this->statuts();

        // Palette de badge par couleur sémantique (statut de présence).
        $badge = [
            'gray' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
            'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
            'info' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400',
            'danger' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400',
        ];
    @endphp

    {{-- Rafraîchi automatiquement : les signatures apparaissent en direct. --}}
    <div wire:poll.10s class="space-y-6">

        {{-- Bandeau de tête : infos séance + compteurs --}}
        <div class="flex flex-col gap-5 rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <p class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ $s->promotion?->formation?->libelle ?? 'Formation' }} · {{ $s->promotion?->libelle }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $s->date->format('d/m/Y') }}
                    @if ($s->heure_debut) · {{ \Illuminate\Support\Carbon::parse($s->heure_debut)->format('H\hi') }}@if ($s->heure_fin)–{{ \Illuminate\Support\Carbon::parse($s->heure_fin)->format('H\hi') }}@endif @endif
                    @if ($s->formateur) · {{ $s->formateur->name }} @endif
                    @if ($s->libelle) · {{ $s->libelle }} @endif
                </p>
            </div>

            <div class="flex items-center gap-6">
                {{-- Anneau de progression des signatures --}}
                <div class="flex items-center gap-3">
                    <div class="relative h-16 w-16">
                        <svg viewBox="0 0 36 36" class="h-16 w-16 -rotate-90">
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3" class="text-gray-200 dark:text-white/10" />
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                    class="text-primary-600 dark:text-primary-500"
                                    stroke-dasharray="{{ $stats['pct_signes'] }} 100" />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-gray-950 dark:text-white">
                            {{ $stats['signes'] }}/{{ $stats['total'] }}
                        </span>
                    </div>
                    <div class="text-sm">
                        <p class="font-semibold text-gray-950 dark:text-white">Signatures</p>
                        <p class="text-gray-500 dark:text-gray-400">{{ $stats['pct_signes'] }} % signé</p>
                    </div>
                </div>

                {{-- Taux de présence --}}
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-950 dark:text-white">
                        {{ $stats['taux_presence'] === null ? '—' : $stats['taux_presence'].'%' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Présence</p>
                </div>
            </div>
        </div>

        {{-- Cartes apprenants --}}
        @if (count($lignes) === 0)
            <p class="rounded-xl border border-dashed border-gray-200 p-10 text-center text-sm text-gray-400 dark:border-white/10">
                Aucun apprenant rattaché à cette séance.
            </p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($lignes as $l)
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                        {{-- Avatar initiales --}}
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                            {{ $l['initiales'] }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $l['nom'] }}</p>

                            {{-- État de signature --}}
                            @if ($l['signe'])
                                <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    ✓ Signé{{ $l['signed_at'] ? ' à '.$l['signed_at']->format('H\hi') : '' }}
                                </p>
                            @else
                                <p class="text-xs text-amber-600 dark:text-amber-400">⏳ En attente de signature</p>
                            @endif

                            {{-- Sélecteur de statut (un clic) --}}
                            <select
                                wire:change="definirStatut({{ $l['id'] }}, $event.target.value)"
                                class="mt-2 w-full rounded-lg border-0 py-1 text-xs font-medium ring-1 ring-inset {{ $badge[$l['couleur']] ?? $badge['gray'] }} focus:ring-2 focus:ring-primary-500">
                                @foreach ($statuts as $val => $label)
                                    <option value="{{ $val }}" @selected($l['statut'] === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>