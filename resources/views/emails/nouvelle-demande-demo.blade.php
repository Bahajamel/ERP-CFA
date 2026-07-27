@component('mail::message')
# Nouvelle demande de démonstration

**{{ $demande->nomComplet() }}**@if ($demande->job_title) — {{ $demande->job_title }}@endif
de **{{ $demande->organization_name }}** souhaite une démonstration de Meridian CFA.

@component('mail::panel')
**Contact**
E-mail : {{ $demande->email }}
@if ($demande->phone)
Téléphone : {{ $demande->phone }}
@endif

**Établissement**
@if ($demande->learner_count)
Apprenants : {{ $demande->learner_count }}
@endif
@if ($demande->main_need)
Besoin principal : {{ $demande->main_need }}
@endif
@endcomponent

@if ($demande->message)
**Message**

> {{ $demande->message }}
@endif

Demande reçue le {{ $demande->created_at?->format('d/m/Y à H:i') }}.

@component('mail::button', ['url' => url('/editeur')])
Ouvrir dans l'espace éditeur
@endcomponent

@endcomponent