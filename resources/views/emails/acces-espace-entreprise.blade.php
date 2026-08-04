@component('mail::message')
# Bonjour,

Votre espace entreprise **{{ $nomCfa }}** est prêt pour **{{ $entreprise }}**. Vous y
suivez à tout moment :

- vos **alternants** et leur **assiduité** (absences signalées),
- vos **documents** (conventions, contrats, CERFA),
- vos **factures**.

@component('mail::button', ['url' => $lien])
Accéder à mon espace
@endcomponent

Ce lien est **personnel** : ne le partagez pas. Si le bouton ne fonctionne pas,
copiez-collez cette adresse dans votre navigateur :

{{ $lien }}

Cordialement,<br>
L'équipe {{ $nomCfa }}
@endcomponent