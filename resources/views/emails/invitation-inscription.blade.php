@component('mail::message')
# Bonjour {{ $prenom }},

Votre admission au CFA est **validée** 🎉 et vous avez été affecté(e) à la classe
**{{ $classe }}**.

Dernière étape : choisissez les matières que vous souhaitez suivre au sein de la
formation.

@component('mail::button', ['url' => $lien])
Choisir mes matières
@endcomponent

Ce lien est personnel et valable **{{ $jours }} jours**. Si le bouton ne
fonctionne pas, copiez-collez cette adresse dans votre navigateur :

{{ $lien }}

Merci,<br>
L'équipe du CFA
@endcomponent
