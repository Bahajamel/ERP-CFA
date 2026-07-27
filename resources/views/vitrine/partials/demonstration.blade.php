{{-- Section démonstration + formulaire.

     Le formulaire est un composant Livewire (App\Livewire\DemandeDemoForm) :
     validation serveur, enregistrement de la demande, notification de l'équipe
     éditeur, pot de miel et limitation par IP. --}}
<section id="demonstration" class="scroll-mt-20 bg-gradient-to-br from-indigo-600 to-violet-700">
    <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            {{-- Accroche --}}
            <div data-reveal>
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                    Découvrez comment Meridian CFA peut simplifier votre organisation
                </h2>
                <p class="mt-4 text-lg text-indigo-100">
                    Présentez-nous vos processus actuels et découvrez une démonstration adaptée à votre CFA.
                </p>
                <ul class="mt-8 space-y-3">
                    @foreach (['Une démonstration personnalisée à votre activité', 'Des réponses concrètes à vos besoins', 'Sans engagement'] as $arg)
                        <li class="flex items-center gap-3 text-indigo-50">
                            <svg class="h-5 w-5 shrink-0 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            {{ $arg }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Formulaire réel --}}
            <div data-reveal>
                @livewire('demande-demo-form')
            </div>
        </div>
    </div>
</section>