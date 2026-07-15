@component('mail::message')
@isset($destinataire)
Bonjour {{ $destinataire }},
@else
Bonjour,
@endisset

@if (filled($message))
{!! nl2br(e($message)) !!}
@else
Vous trouverez ci-joint le contrat d'apprentissage à signer.
@endif

@component('mail::panel')
**Apprenti :** {{ $apprenti }}
@if (filled($entreprise))
**Entreprise :** {{ $entreprise }}
@endif
@if (filled($formation))
**Formation :** {{ $formation }}
@endif
@if (filled($dateDebut))
**Début du contrat :** {{ $dateDebut }}
@endif
@endcomponent

**Documents joints à ce message :**
@foreach ($pieces as $piece)
- {{ $piece }}
@endforeach

**Ce qu'il vous reste à faire :**

1. Imprimez les documents joints.
2. Signez aux emplacements prévus (et faites-les signer par les autres parties si vous les recevez en premier).
3. Scannez les documents signés — une photo nette et lisible convient également.
4. Répondez à ce message en joignant les documents signés.

Dès réception, nous finalisons votre dossier. N'hésitez pas à répondre à ce
message pour toute question.

Cordialement,<br>
{{ $cfa }}
@endcomponent
