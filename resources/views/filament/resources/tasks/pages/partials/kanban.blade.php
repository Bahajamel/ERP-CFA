{{-- Colonnes Kanban : $kanban (statut, total, apercu), $badge, $detailId. --}}
<div class="ta-kanban-cols">
    @foreach ($kanban as $col)
        <div class="ta-kcol" wire:key="kcol-{{ $col['statut']->value }}">
            <div class="ta-kcol-head">
                <span class="ta-badge {{ $badge[$col['statut']->getColor()] }}">{{ $col['statut']->getLabel() }}</span>
                <span class="ta-kcount">{{ $col['total'] }}</span>
            </div>

            @foreach ($col['apercu'] as $t)
                <div class="ta-kcard {{ $t['id'] === $detailId ? 'is-selected' : '' }}" wire:key="kcard-{{ $t['id'] }}" wire:click="selectionner({{ $t['id'] }})">
                    {{ $t['titre'] }}
                </div>
            @endforeach

            @if ($col['total'] > $col['apercu']->count())
                <span class="ta-kmore" wire:click="choisirOnglet('toutes')">+ {{ $col['total'] - $col['apercu']->count() }} autre{{ $col['total'] - $col['apercu']->count() > 1 ? 's' : '' }} tâche{{ $col['total'] - $col['apercu']->count() > 1 ? 's' : '' }}</span>
            @endif
        </div>
    @endforeach
</div>
