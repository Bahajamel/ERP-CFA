{{-- Outils de topbar : badge d'environnement + création rapide (gated par permissions). --}}
@php
    $user = auth()->user();
    $liens = collect([
        ['label' => 'Nouveau candidat', 'permission' => 'access_candidates', 'url' => route('filament.admin.resources.candidates.create'), 'icon' => 'heroicon-m-user-plus'],
        ['label' => 'Nouvelle entreprise', 'permission' => 'access_companies', 'url' => route('filament.admin.resources.companies.create'), 'icon' => 'heroicon-m-building-office-2'],
        ['label' => 'Nouveau besoin', 'permission' => 'access_needs', 'url' => route('filament.admin.resources.needs.create'), 'icon' => 'heroicon-m-briefcase'],
        ['label' => 'Nouvelle proposition', 'permission' => 'access_matching', 'url' => route('filament.admin.resources.matchings.create'), 'icon' => 'heroicon-m-arrows-right-left'],
        ['label' => 'Nouveau contrat', 'permission' => 'access_contracts', 'url' => route('filament.admin.resources.contracts.create'), 'icon' => 'heroicon-m-document-text'],
    ])->filter(fn (array $l): bool => $user?->can($l['permission']) ?? false);
@endphp

<div class="cfa-topbar-tools">
    @unless (app()->isProduction())
        <span class="cfa-env-badge" title="Environnement de démonstration">Démo</span>
    @endunless

    @if ($liens->isNotEmpty())
        <x-filament::dropdown placement="bottom-end">
            <x-slot name="trigger">
                <button type="button" class="cfa-create-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                    </svg>
                    Créer
                </button>
            </x-slot>

            <x-filament::dropdown.list>
                @foreach ($liens as $lien)
                    <x-filament::dropdown.list.item :href="$lien['url']" :icon="$lien['icon']" tag="a">
                        {{ $lien['label'] }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    @endif
</div>
