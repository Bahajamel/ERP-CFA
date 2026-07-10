{{-- Identité utilisateur affichée à gauche de l'avatar (nom + rôle métier). --}}
@php
    $u = auth()->user();
    $poste = $u?->getRoleNames()->first() ?? 'Utilisateur';
@endphp

@if ($u)
    <div class="cfa-user-identity" aria-hidden="true">
        <span class="cfa-user-name">{{ $u->name }}</span>
        <span class="cfa-user-role">{{ $poste }}</span>
    </div>
@endif
