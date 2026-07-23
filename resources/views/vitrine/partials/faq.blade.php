{{-- FAQ en accordéon accessible (JS vanilla, cf. layout). Réponses honnêtes,
     alignées sur les fonctionnalités réelles de l'ERP. --}}
@php
    $questions = [
        ['À qui s\'adresse Meridian CFA ?', 'Aux CFA, centres de formation et organismes de formation qui souhaitent centraliser la gestion de leurs candidats, entreprises partenaires, contrats, formations et financements dans une seule plateforme.'],
        ['Peut-on adapter la plateforme à notre organisation ?', 'Oui. Vous pouvez créer des tableaux personnalisés, ajouter des colonnes, ajuster les statuts et configurer les vues pour coller à vos processus, sans développement spécifique.'],
        ['La plateforme gère-t-elle les candidats et les entreprises ?', 'Oui. Meridian CFA couvre le suivi des candidats, la gestion des entreprises partenaires, leurs besoins en recrutement, les offres d\'alternance et le matching entre candidats et besoins.'],
        ['Peut-on suivre les contrats et les dossiers OPCO ?', 'Oui. L\'ERP suit les contrats, les pièces manquantes, les dossiers OPCO, les échéances et les ruptures, avec des relances automatiques pour ne rien oublier.'],
        ['Peut-on créer des tableaux personnalisés ?', 'Oui. La création de tableaux et de colonnes personnalisables fait partie des fonctionnalités : chaque CFA organise ses vues selon ses méthodes de travail.'],
        ['Comment sont protégées les données ?', 'Par des rôles et permissions, une authentification multifacteur pour les comptes sensibles, des documents privés, des liens sécurisés temporaires et un historique des actions. La solution suit les bonnes pratiques de sécurité.'],
        ['Peut-on gérer plusieurs établissements ?', 'Oui. L\'architecture multi-établissements permet de cloisonner les données de chaque structure tout en gardant une administration cohérente.'],
        ['La solution fonctionne-t-elle à distance ?', 'Oui. Meridian CFA est une application web accessible depuis un navigateur : vos équipes y accèdent au bureau comme à distance, avec les mêmes droits.'],
        ['Une démonstration est-elle disponible ?', 'Oui. Renseignez le formulaire de demande de démonstration : notre équipe vous recontacte pour organiser une présentation adaptée à votre CFA.'],
    ];
@endphp

<section id="faq" class="scroll-mt-20 bg-white">
    <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="text-center" data-reveal>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">FAQ</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Questions fréquentes</h2>
        </div>

        <div class="mt-12 divide-y divide-slate-200 border-y border-slate-200" data-reveal>
            @foreach ($questions as $i => [$q, $r])
                <div>
                    <h3>
                        <button type="button" data-accordion-btn aria-expanded="false" aria-controls="faq-panneau-{{ $i }}"
                            class="flex w-full items-center justify-between gap-4 py-5 text-left">
                            <span class="text-base font-semibold text-slate-900">{{ $q }}</span>
                            <svg data-chevron class="h-5 w-5 shrink-0 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                    </h3>
                    <div id="faq-panneau-{{ $i }}" hidden class="pb-5 pr-9">
                        <p class="text-sm leading-relaxed text-slate-600">{{ $r }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-sm text-slate-600" data-reveal>
            Une autre question ?
            <a href="#demonstration" class="font-semibold text-indigo-600 hover:text-indigo-500">Contactez notre équipe</a>.
        </p>
    </div>
</section>