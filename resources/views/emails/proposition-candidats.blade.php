@component('mail::message')
{!! nl2br(e($message)) !!}

@component('mail::panel')
**Profils proposés pour le poste « {{ $poste }} »**

@foreach ($candidats as $c)
- **{{ $c->nom_complet }}** — {{ $c->formationVisee?->libelle ?? 'formation à préciser' }}@if ($c->disponibilite) · disponible : {{ $c->disponibilite }}@endif
@endforeach
@endcomponent

Nos équipes restent à votre disposition pour organiser les entretiens et vous
transmettre les CV détaillés.

@isset($responsable)
Cordialement,<br>
{{ $responsable }}
@endisset
@endcomponent
