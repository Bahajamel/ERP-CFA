@component('mail::message')
# Bonjour {{ $prenom }},

Votre espace personnel **{{ $nomCfa }}** est prêt. Vous y retrouvez à tout moment :

- votre **planning** de séances,
- votre **assiduité** et la signature de vos présences,
- vos **documents** (convention, contrat, bulletins, calendrier…).

@component('mail::button', ['url' => $lien])
Accéder à mon espace
@endcomponent

Ce lien vous est **personnel** : ne le partagez pas. Si le bouton ne fonctionne
pas, copiez-collez cette adresse dans votre navigateur :

{{ $lien }}

À bientôt,<br>
L'équipe {{ $nomCfa }}
@endcomponent