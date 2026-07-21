@extends('entreprise.layout')

@section('title', 'Votre besoin en alternance')
@section('heading', 'Décrivez votre besoin')
@section('subheading', 'Dites-nous quel profil vous recherchez : nous vous proposerons des candidats adaptés.')

@section('content')
    @include('entreprise.partials.etapes', ['etape' => 2])

    <div class="mb-6 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-600/10">
        <p><span class="font-semibold">{{ $company->raison_sociale }}</span> est bien enregistrée ✅</p>
        <p class="mt-0.5 text-emerald-700">Dernière étape : le poste que vous souhaitez pourvoir.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-600/10">
            <p class="font-semibold">Merci de corriger les points suivants :</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('entreprise.besoin.store') }}" class="space-y-8">
        @csrf
        <div class="hidden" aria-hidden="true">
            <label>Ne rien saisir <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-slate-900">Le poste recherché</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    @include('candidature.partials.field', [
                        'name' => 'intitule_poste',
                        'label' => 'Intitulé du poste *',
                        'type' => 'text',
                        'required' => true,
                        'placeholder' => 'ex : Apprenti boulanger, Développeur web',
                    ])
                </div>

                <div>
                    <label for="formation_id" class="block text-sm font-medium text-slate-700">Formation visée</label>
                    <select id="formation_id" name="formation_id"
                        class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Je ne sais pas encore —</option>
                        @foreach ($formations as $id => $libelle)
                            <option value="{{ $id }}" @selected(old('formation_id') == $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Laissez vide si vous hésitez : nous vous conseillerons.</p>
                </div>

                <div>
                    <label for="nb_postes" class="block text-sm font-medium text-slate-700">Nombre de postes *</label>
                    <input type="number" id="nb_postes" name="nb_postes" value="{{ old('nb_postes', 1) }}"
                        min="1" max="99" required
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('nb_postes') border-rose-400 @else border-slate-300 @enderror">
                    @error('nb_postes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="date_demarrage" class="block text-sm font-medium text-slate-700">Date de démarrage souhaitée</label>
                    <input type="date" id="date_demarrage" name="date_demarrage" value="{{ old('date_demarrage') }}"
                        min="{{ now()->toDateString() }}"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date_demarrage') border-rose-400 @else border-slate-300 @enderror">
                    @error('date_demarrage')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                @include('candidature.partials.field', [
                    'name' => 'rythme',
                    'label' => 'Rythme d\'alternance souhaité',
                    'type' => 'text',
                    'placeholder' => 'ex : 2 j CFA / 3 j entreprise',
                ])

                {{-- Lieu de la mission, pré-rempli avec l'adresse de l'entreprise :
                     le siège n'est pas toujours le lieu de travail. --}}
                <div class="relative sm:col-span-2">
                    <label for="localisation" class="block text-sm font-medium text-slate-700">Lieu de la mission</label>
                    <input type="text" id="localisation" name="localisation"
                        value="{{ old('localisation', $company->adresse) }}"
                        autocomplete="off" placeholder="Commencez à taper l'adresse…"
                        class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <ul id="localisation-suggestions"
                        class="absolute z-20 mt-1 hidden w-full overflow-hidden rounded-lg border border-slate-200 bg-white text-sm shadow-lg"></ul>
                    <p class="mt-1 text-xs text-slate-400">Pré-rempli avec l'adresse de votre entreprise — modifiez-le si l'alternant travaillera ailleurs.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="prerequis" class="block text-sm font-medium text-slate-700">Missions et prérequis</label>
                    <textarea id="prerequis" name="prerequis" rows="5"
                        placeholder="Décrivez les missions confiées, le niveau attendu, les compétences indispensables…"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('prerequis') border-rose-400 @else border-slate-300 @enderror">{{ old('prerequis') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Plus c'est précis, plus les profils proposés seront pertinents.</p>
                    @error('prerequis')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

        <div class="flex flex-col gap-3 sm:flex-row-reverse sm:items-center">
            <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto sm:flex-1">
                Envoyer mon besoin
            </button>
            <a href="{{ route('entreprise.merci') }}"
                class="w-full rounded-lg px-4 py-2.5 text-center text-sm font-medium text-slate-500 transition hover:text-slate-700 sm:w-auto">
                Passer cette étape
            </a>
        </div>
        <p class="text-xs text-slate-400">
            Vous pourrez toujours nous transmettre votre besoin plus tard : votre entreprise est déjà enregistrée.
        </p>
    </form>

    <script>
        // ---- Lieu de mission : autocomplétion Base Adresse Nationale ----
        // Même comportement qu'à l'étape 1 (champ « Adresse »).
        (function () {
            var champ = document.getElementById('localisation');
            var liste = document.getElementById('localisation-suggestions');
            var minuteur = null;

            function fermer() {
                liste.classList.add('hidden');
                liste.replaceChildren();
            }

            function afficher(resultats) {
                liste.replaceChildren();
                if (!resultats.length) { fermer(); return; }

                resultats.forEach(function (r) {
                    var item = document.createElement('li');
                    // textContent uniquement : les données externes ne sont jamais interprétées en HTML.
                    item.textContent = r.properties.label;
                    item.className = 'cursor-pointer px-3 py-2 hover:bg-indigo-50';
                    item.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        champ.value = r.properties.label;
                        fermer();
                    });
                    liste.appendChild(item);
                });
                liste.classList.remove('hidden');
            }

            champ.addEventListener('input', function () {
                clearTimeout(minuteur);
                var q = champ.value.trim();
                if (q.length < 4) { fermer(); return; }

                minuteur = setTimeout(function () {
                    fetch('https://api-adresse.data.gouv.fr/search/?limit=5&q=' + encodeURIComponent(q))
                        .then(function (rep) { return rep.ok ? rep.json() : { features: [] }; })
                        .then(function (json) { afficher(json.features || []); })
                        .catch(fermer);
                }, 300);
            });

            champ.addEventListener('blur', function () { setTimeout(fermer, 150); });
            champ.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermer(); });
        })();
    </script>
@endsection
