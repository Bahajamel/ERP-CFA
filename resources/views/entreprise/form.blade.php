@extends('entreprise.layout')

@section('title', 'Devenir entreprise partenaire')
@section('heading', 'Devenez entreprise partenaire')
@section('subheading', 'Renseignez votre SIRET : nous pré-remplissons vos informations et votre OPCO automatiquement.')

@section('content')
    <p class="mb-6 text-sm text-slate-500">Les champs marqués <span class="font-semibold text-rose-500">*</span> sont obligatoires.</p>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-600/10">
            <p class="font-semibold">Merci de corriger les points suivants :</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('entreprise.store') }}" class="space-y-8">
        @csrf
        <div class="hidden" aria-hidden="true">
            <label>Ne rien saisir <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-slate-900">Votre entreprise</legend>

            {{-- SIRET + auto-remplissage --}}
            <div>
                <label for="siret" class="block text-sm font-medium text-slate-700">SIRET *</label>
                <div class="mt-1 flex gap-2">
                    <input type="text" id="siret" name="siret" value="{{ old('siret') }}" inputmode="numeric" placeholder="14 chiffres" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" onclick="recupererSiret()"
                        class="whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        Récupérer les infos
                    </button>
                </div>
                <p id="siret-statut" class="mt-1 text-xs text-slate-500">Saisissez le SIRET puis cliquez pour pré-remplir automatiquement.</p>
                @error('siret')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @include('candidature.partials.field', ['name' => 'raison_sociale', 'label' => 'Raison sociale *', 'type' => 'text', 'required' => true])
                @include('candidature.partials.field', ['name' => 'secteur', 'label' => 'Secteur (code NAF)', 'type' => 'text'])
                <div class="sm:col-span-2">
                    @include('candidature.partials.field', ['name' => 'adresse', 'label' => 'Adresse', 'type' => 'text'])
                </div>
                <div class="sm:col-span-2">
                    <label for="opco_id" class="block text-sm font-medium text-slate-700">OPCO</label>
                    <select id="opco_id" name="opco_id"
                        class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— À déterminer —</option>
                        @foreach ($opcos as $id => $nom)
                            <option value="{{ $id }}" @selected(old('opco_id') == $id)>{{ $nom }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Rempli automatiquement depuis le SIRET quand c'est possible.</p>
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-slate-900">Contact principal</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                @include('candidature.partials.field', ['name' => 'contact_nom', 'label' => 'Nom *', 'type' => 'text', 'required' => true])
                @include('candidature.partials.field', ['name' => 'contact_prenom', 'label' => 'Prénom', 'type' => 'text'])
                @include('candidature.partials.field', ['name' => 'contact_email', 'label' => 'Email', 'type' => 'email'])
                @include('candidature.partials.field', ['name' => 'contact_telephone', 'label' => 'Téléphone', 'type' => 'tel'])
                @include('candidature.partials.field', ['name' => 'contact_fonction', 'label' => 'Fonction', 'type' => 'text'])
            </div>
            <p class="text-xs text-slate-500">Indiquez au moins un email <strong>ou</strong> un téléphone.</p>
        </fieldset>

        <button type="submit"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Devenir partenaire
        </button>
    </form>

    <script>
        async function recupererSiret() {
            const siret = document.getElementById('siret').value.replace(/\D/g, '');
            const statut = document.getElementById('siret-statut');
            if (siret.length !== 14) { statut.textContent = 'Entrez un SIRET à 14 chiffres.'; return; }
            statut.textContent = 'Recherche en cours…';
            try {
                const r = await fetch(@js(route('entreprise.lookup')) + '?siret=' + siret);
                const d = await r.json();
                if (!d.trouve) { statut.textContent = 'Entreprise introuvable — remplissez manuellement.'; return; }
                if (d.raison_sociale) document.getElementById('raison_sociale').value = d.raison_sociale;
                if (d.adresse) document.getElementById('adresse').value = d.adresse;
                if (d.secteur) document.getElementById('secteur').value = d.secteur;
                if (d.opco_id) document.getElementById('opco_id').value = d.opco_id;
                let msg = 'Informations récupérées ✓';
                if (!d.opco_id && d.opco_nom) msg += ' · OPCO détecté : ' + d.opco_nom + ' (à sélectionner)';
                else if (!d.opco_id) msg += ' · OPCO à sélectionner';
                statut.textContent = msg;
            } catch (e) {
                statut.textContent = 'Impossible de contacter le service. Remplissez manuellement.';
            }
        }
    </script>
@endsection
