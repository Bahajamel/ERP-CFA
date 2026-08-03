@extends('candidature.layout')

@section('title', 'Signer ma présence')
@section('heading', 'Feuille d\'émargement')
@section('subheading', 'Signez pour attester de votre présence à cette séance.')

@section('content')
    @php $seance = $presence->seance; @endphp

    <div class="mb-6 rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-100">
        <p class="text-sm text-indigo-900">
            Bonjour <strong>{{ $presence->candidate?->prenom }} {{ $presence->candidate?->nom }}</strong>.
        </p>
        <dl class="mt-3 grid gap-x-4 gap-y-1 text-sm text-indigo-900 sm:grid-cols-2">
            <div><dt class="inline text-indigo-500">Formation :</dt> <dd class="inline font-semibold">{{ $seance?->promotion?->formation?->libelle ?? '—' }}</dd></div>
            <div><dt class="inline text-indigo-500">Séance :</dt> <dd class="inline font-semibold">{{ $seance?->libelle ?? '—' }}</dd></div>
            <div><dt class="inline text-indigo-500">Date :</dt> <dd class="inline font-semibold">{{ $seance?->date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="inline text-indigo-500">Formateur :</dt> <dd class="inline font-semibold">{{ $seance?->formateur?->name ?? '—' }}</dd></div>
        </dl>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 ring-1 ring-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('emargement.signer.store', $token) }}" id="form-signature">
        @csrf
        <input type="hidden" name="signature" id="signature-data">

        <label class="mb-2 block text-sm font-semibold text-gray-800">Votre signature</label>
        <div class="relative rounded-xl border-2 border-dashed border-gray-300 bg-gray-50" style="touch-action:none;">
            <canvas id="pad" class="block w-full rounded-xl" style="height:220px; touch-action:none;"></canvas>
            <span id="pad-hint" class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                Signez ici avec votre doigt ou la souris
            </span>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <button type="button" id="effacer" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                Effacer
            </button>
            <button type="submit"
                    class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">
                Valider ma signature
            </button>
        </div>
        <p class="mt-4 text-xs text-slate-400">
            Votre signature et l'horodatage servent uniquement de preuve de présence à cette séance.
        </p>
    </form>

    <script>
        (function () {
            var canvas = document.getElementById('pad');
            var ctx = canvas.getContext('2d');
            var hint = document.getElementById('pad-hint');
            var form = document.getElementById('form-signature');
            var champ = document.getElementById('signature-data');
            var dessine = false, enCours = false, dernier = null;

            // Résolution nette (Retina) : dimensionne le canvas selon son affichage.
            function dimensionner() {
                var ratio = window.devicePixelRatio || 1;
                var rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2.2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#1e293b';
            }
            dimensionner();

            function point(e) {
                var rect = canvas.getBoundingClientRect();
                return { x: e.clientX - rect.left, y: e.clientY - rect.top };
            }

            function debut(e) {
                enCours = true;
                dernier = point(e);
                e.preventDefault();
            }
            function trace(e) {
                if (!enCours) return;
                var p = point(e);
                ctx.beginPath();
                ctx.moveTo(dernier.x, dernier.y);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                dernier = p;
                dessine = true;
                if (hint) hint.style.display = 'none';
                e.preventDefault();
            }
            function fin() { enCours = false; }

            canvas.addEventListener('pointerdown', debut);
            canvas.addEventListener('pointermove', trace);
            window.addEventListener('pointerup', fin);

            document.getElementById('effacer').addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                dessine = false;
                if (hint) hint.style.display = '';
            });

            form.addEventListener('submit', function (e) {
                if (!dessine) {
                    e.preventDefault();
                    alert('Merci de signer dans le cadre avant de valider.');
                    return;
                }
                champ.value = canvas.toDataURL('image/png');
            });
        })();
    </script>
@endsection