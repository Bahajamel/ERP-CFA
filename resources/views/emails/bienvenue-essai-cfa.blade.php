@component('mail::message')
# Bienvenue sur Meridian CFA 🚀

Votre espace **{{ $nomCfa }}** est prêt. Vous pouvez dès maintenant découvrir
l'ERP et le prendre en main pendant votre essai gratuit.

@component('mail::button', ['url' => $url])
Accéder à mon espace
@endcomponent

## Vos identifiants

- **Adresse :** {{ $url }}
- **Identifiant :** {{ $email }}
@if ($motDePasse)
- **Mot de passe temporaire :** {{ $motDePasse }}

Pour votre sécurité, changez ce mot de passe dès votre première connexion.
@else

Connectez-vous avec le mot de passe de votre compte existant.
@endif

@if ($dateFinEssai)
Votre essai gratuit est ouvert **jusqu'au {{ $dateFinEssai }}**. Nous
reviendrons vers vous avant l'échéance pour faire le point.
@endif

À très bientôt,<br>
L'équipe Meridian CFA
@endcomponent