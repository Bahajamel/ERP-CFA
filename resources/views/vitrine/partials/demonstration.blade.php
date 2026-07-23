{{-- Section démonstration + formulaire.

     ⚠️ PHASE 1 (vitrine seule) : le formulaire est VISUEL. À l'envoi, un état de
     confirmation s'affiche côté client mais RIEN N'EST ENREGISTRÉ. La phase 2
     le remplacera par un composant Livewire (validation serveur, stockage en
     base, notification à l'équipe, anti-spam). --}}
@php
    $champs = [
        ['prenom', 'Prénom', 'text', true, 'given-name'],
        ['nom', 'Nom', 'text', true, 'family-name'],
        ['fonction', 'Fonction', 'text', false, 'organization-title'],
        ['etablissement', 'Nom de l\'établissement', 'text', true, 'organization'],
        ['email', 'Adresse e-mail professionnelle', 'email', true, 'email'],
        ['telephone', 'Téléphone', 'tel', false, 'tel'],
    ];
@endphp

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

            {{-- Formulaire (visuel — phase 1) --}}
            <div data-reveal class="rounded-2xl bg-white p-6 shadow-2xl shadow-indigo-950/20 sm:p-8" data-demo-form>
                {{-- Succès (masqué par défaut) --}}
                <div data-demo-success hidden class="py-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">Merci pour votre demande</h3>
                    <p class="mt-2 text-sm text-slate-600">Notre équipe vous recontactera prochainement pour organiser votre démonstration.</p>
                </div>

                {{-- Le formulaire --}}
                <form data-demo-fields class="space-y-4" novalidate>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($champs as [$name, $label, $type, $requis, $autocomplete])
                            <div @if (in_array($name, ['etablissement', 'email'])) class="sm:col-span-2" @endif>
                                <label for="demo_{{ $name }}" class="block text-sm font-medium text-slate-700">
                                    {{ $label }}@if ($requis) <span class="text-rose-500">*</span>@endif
                                </label>
                                <input type="{{ $type }}" id="demo_{{ $name }}" name="{{ $name }}" autocomplete="{{ $autocomplete }}" @if ($requis) required @endif
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        @endforeach

                        <div>
                            <label for="demo_apprenants" class="block text-sm font-medium text-slate-700">Nombre d'apprenants</label>
                            <select id="demo_apprenants" name="learner_count" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Sélectionner —</option>
                                @foreach (['1 à 50', '50 à 200', '200 à 500', 'Plus de 500'] as $tranche)
                                    <option value="{{ $tranche }}">{{ $tranche }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="demo_besoin" class="block text-sm font-medium text-slate-700">Besoin principal</label>
                            <select id="demo_besoin" name="main_need" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Sélectionner —</option>
                                @foreach (['Gestion des candidats', 'Entreprises et alternance', 'Contrats et OPCO', 'Scolarité et pédagogie', 'Finance et facturation', 'Pilotage global'] as $besoin)
                                    <option value="{{ $besoin }}">{{ $besoin }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="demo_message" class="block text-sm font-medium text-slate-700">Message</label>
                        <textarea id="demo_message" name="message" rows="3" placeholder="Décrivez brièvement votre organisation et vos besoins…"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>

                    <label class="flex items-start gap-2.5 text-xs text-slate-500">
                        <input type="checkbox" name="consent" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>J'accepte que mes informations soient utilisées pour être recontacté(e). Consultez notre <a href="{{ route('vitrine.confidentialite') }}" class="font-medium text-indigo-600 underline">politique de confidentialité</a>.</span>
                    </label>

                    <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Demander une démonstration
                    </button>
                    <p class="text-center text-xs text-slate-400">Réponse sous 48 h ouvrées · Sans engagement</p>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    // PHASE 1 : confirmation visuelle uniquement (aucun enregistrement).
    // Remplacé par le composant Livewire en phase 2.
    (function () {
        var bloc = document.querySelector('[data-demo-form]');
        if (!bloc) return;
        var form = bloc.querySelector('[data-demo-fields]');
        var succes = bloc.querySelector('[data-demo-success]');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }
            form.hidden = true;
            succes.hidden = false;
            bloc.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    })();
</script>