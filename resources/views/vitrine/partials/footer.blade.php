{{-- Pied de page de la vitrine. Liens réels uniquement ; aucune identité
     juridique inventée (à compléter quand elle sera fournie). --}}
@php($connecte = auth()->check())
<footer class="border-t border-slate-800 bg-slate-950 text-slate-400">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            {{-- Marque --}}
            <div class="lg:col-span-1">
                @include('vitrine.partials.logo', ['ton' => 'clair'])
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">
                    L'ERP tout-en-un pour piloter votre CFA : candidats, entreprises, contrats,
                    formations, financements et obligations administratives, au même endroit.
                </p>
            </div>

            {{-- Solution --}}
            <div>
                <h3 class="text-sm font-semibold text-white">Solution</h3>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="#solution" class="transition hover:text-white">Présentation</a></li>
                    <li><a href="#fonctionnalites" class="transition hover:text-white">Fonctionnalités</a></li>
                    <li><a href="#pour-qui" class="transition hover:text-white">Pour qui ?</a></li>
                    <li><a href="#securite" class="transition hover:text-white">Sécurité</a></li>
                </ul>
            </div>

            {{-- Ressources --}}
            <div>
                <h3 class="text-sm font-semibold text-white">Ressources</h3>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="#faq" class="transition hover:text-white">FAQ</a></li>
                    <li><a href="#demonstration" class="transition hover:text-white">Demander une démonstration</a></li>
                    <li><a href="#demonstration" class="transition hover:text-white">Nous contacter</a></li>
                    <li>
                        <a href="{{ $connecte ? url('/admin') : route('filament.admin.auth.login') }}" class="transition hover:text-white">
                            {{ $connecte ? 'Mon espace' : 'Se connecter' }}
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Légal --}}
            <div>
                <h3 class="text-sm font-semibold text-white">Informations</h3>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="{{ route('vitrine.mentions') }}" class="transition hover:text-white">Mentions légales</a></li>
                    <li><a href="{{ route('vitrine.confidentialite') }}" class="transition hover:text-white">Politique de confidentialité</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-slate-800 pt-6 sm:flex-row">
            <p class="text-xs text-slate-500">© {{ now()->year }} Meridian CFA. Tous droits réservés.</p>
            <p class="text-xs text-slate-500">Conçu pour les CFA et organismes de formation.</p>
        </div>
    </div>
</footer>