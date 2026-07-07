@extends('candidature.layout')

@section('title', 'Rejoignez notre CFA')
@section('heading', 'Rejoignez notre CFA')
@section('subheading', 'Déposez votre candidature en quelques minutes — notre équipe revient vers vous rapidement.')

@section('content')
    {{-- Arguments (feel « landing recrutement ») --}}
    <div class="mb-8 grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['Alternance rémunérée', 'Formez-vous en étant payé(e)'],
            ['Accompagnement', 'Un référent vous suit tout du long'],
            ['Diplôme reconnu', 'Titre inscrit au RNCP'],
        ] as [$titre, $sous])
            <div class="rounded-xl bg-indigo-50 p-3 text-center ring-1 ring-indigo-100">
                <p class="text-sm font-semibold text-indigo-900">{{ $titre }}</p>
                <p class="mt-0.5 text-xs text-indigo-700/80">{{ $sous }}</p>
            </div>
        @endforeach
    </div>

    <p class="mb-6 text-sm text-slate-500">Les champs marqués <span class="font-semibold text-rose-500">*</span> sont obligatoires.</p>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-600/10">
            <p class="font-semibold">Merci de corriger les points suivants :</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('candidature.store') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        <div class="hidden" aria-hidden="true">
            <label>Ne rien saisir ici <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-slate-900">Vos informations</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                @include('candidature.partials.field', ['name' => 'nom', 'label' => 'Nom *', 'type' => 'text', 'required' => true])
                @include('candidature.partials.field', ['name' => 'prenom', 'label' => 'Prénom *', 'type' => 'text', 'required' => true])
                @include('candidature.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
                @include('candidature.partials.field', ['name' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel'])
                @include('candidature.partials.field', ['name' => 'date_naissance', 'label' => 'Date de naissance *', 'type' => 'date', 'required' => true])
                <div>
                    <label for="formation_visee_id" class="block text-sm font-medium text-slate-700">Formation visée *</label>
                    <select id="formation_visee_id" name="formation_visee_id" required
                        class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Choisir —</option>
                        @foreach ($formations as $id => $libelle)
                            <option value="{{ $id }}" @selected(old('formation_visee_id') == $id)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @include('candidature.partials.field', ['name' => 'adresse', 'label' => 'Adresse', 'type' => 'text'])
            <p class="text-xs text-slate-500">Indiquez au moins un email <strong>ou</strong> un téléphone pour être recontacté(e).</p>
        </fieldset>

        <fieldset class="space-y-4">
            <legend class="text-base font-semibold text-slate-900">Vos pièces justificatives</legend>
            @include('candidature.partials.file', ['name' => 'piece_identite', 'label' => "Pièce d'identité *", 'hint' => 'Carte d\'identité, passeport ou titre de séjour (PDF, JPG, PNG — max 5 Mo).'])
            @include('candidature.partials.file', ['name' => 'cv', 'label' => 'CV *', 'hint' => 'PDF, DOC ou DOCX — max 5 Mo.'])
            @include('candidature.partials.file', ['name' => 'carte_vitale', 'label' => 'Carte vitale ou attestation de sécurité sociale *', 'hint' => 'PDF, JPG, PNG — max 5 Mo.'])
            <div id="attestation-field">
                @include('candidature.partials.file', ['name' => 'attestation_projet', 'label' => 'Attestation de création de projet *', 'hint' => 'Obligatoire à partir de 30 ans.'])
            </div>
        </fieldset>

        <button type="submit"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Envoyer ma candidature
        </button>
    </form>

    <script>
        function ageDepuis(valeur) {
            if (!valeur) return null;
            var n = new Date(valeur);
            if (isNaN(n)) return null;
            var t = new Date(), age = t.getFullYear() - n.getFullYear();
            var m = t.getMonth() - n.getMonth();
            if (m < 0 || (m === 0 && t.getDate() < n.getDate())) age--;
            return age;
        }
        function toggleAttestation() {
            var age = ageDepuis(document.getElementById('date_naissance').value);
            document.getElementById('attestation-field').style.display = (age !== null && age >= 30) ? 'block' : 'none';
        }
        var dateNaissance = document.getElementById('date_naissance');
        dateNaissance.addEventListener('change', toggleAttestation);
        dateNaissance.addEventListener('input', toggleAttestation);
        toggleAttestation();
    </script>
@endsection
