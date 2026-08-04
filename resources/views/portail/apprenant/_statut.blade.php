@php
    /** @var \App\Enums\PresenceStatut $statut */
    $classes = match ($statut->getColor()) {
        'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'info' => 'bg-sky-100 text-sky-700',
        'danger' => 'bg-rose-100 text-rose-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $classes }}">
    {{ $statut->getLabel() }}
</span>