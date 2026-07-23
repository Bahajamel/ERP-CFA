{{-- Formulaire « Demander une démonstration » (phase 2 : réellement câblé).
     Reprend la mise en page validée en phase 1 ; la validation est désormais
     faite côté serveur par Livewire, et la demande est enregistrée. --}}
<div class="rounded-2xl bg-white p-6 shadow-2xl shadow-indigo-950/20 sm:p-8">

    @if ($envoye)
        {{-- Confirmation --}}
        <div class="py-10 text-center" role="status">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            </div>
            <h3 class="mt-4 text-lg font-bold text-slate-900">Merci pour votre demande</h3>
            <p class="mt-2 text-sm text-slate-600">
                Notre équipe vous recontactera prochainement pour organiser votre démonstration.
            </p>
        </div>
    @else
        <form wire:submit="envoyer" class="space-y-4">
            {{-- Pot de miel : invisible pour un humain, appât pour les robots. --}}
            <div class="hidden" aria-hidden="true">
                <label>Ne rien saisir <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                {{-- Prénom / Nom --}}
                <div>
                    <label for="demo_first_name" class="block text-sm font-medium text-slate-700">Prénom <span class="text-rose-500">*</span></label>
                    <input type="text" id="demo_first_name" wire:model.blur="first_name" autocomplete="given-name"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('first_name') border-rose-400 @else border-slate-300 @enderror">
                    @error('first_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="demo_last_name" class="block text-sm font-medium text-slate-700">Nom <span class="text-rose-500">*</span></label>
                    <input type="text" id="demo_last_name" wire:model.blur="last_name" autocomplete="family-name"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('last_name') border-rose-400 @else border-slate-300 @enderror">
                    @error('last_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                {{-- Fonction --}}
                <div>
                    <label for="demo_job_title" class="block text-sm font-medium text-slate-700">Fonction</label>
                    <input type="text" id="demo_job_title" wire:model.blur="job_title" autocomplete="organization-title"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('job_title') border-rose-400 @else border-slate-300 @enderror">
                    @error('job_title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                {{-- Téléphone --}}
                <div>
                    <label for="demo_phone" class="block text-sm font-medium text-slate-700">Téléphone</label>
                    <input type="tel" id="demo_phone" wire:model.blur="phone" autocomplete="tel"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('phone') border-rose-400 @else border-slate-300 @enderror">
                    @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                {{-- Établissement --}}
                <div class="sm:col-span-2">
                    <label for="demo_organization" class="block text-sm font-medium text-slate-700">Nom de l'établissement <span class="text-rose-500">*</span></label>
                    <input type="text" id="demo_organization" wire:model.blur="organization_name" autocomplete="organization"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('organization_name') border-rose-400 @else border-slate-300 @enderror">
                    @error('organization_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                {{-- E-mail --}}
                <div class="sm:col-span-2">
                    <label for="demo_email" class="block text-sm font-medium text-slate-700">Adresse e-mail professionnelle <span class="text-rose-500">*</span></label>
                    <input type="email" id="demo_email" wire:model.blur="email" autocomplete="email"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-rose-400 @else border-slate-300 @enderror">
                    @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                {{-- Tranche d'apprenants --}}
                <div>
                    <label for="demo_learner_count" class="block text-sm font-medium text-slate-700">Nombre d'apprenants</label>
                    <select id="demo_learner_count" wire:model="learner_count"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Sélectionner —</option>
                        @foreach (\App\Livewire\DemandeDemoForm::TRANCHES as $tranche)
                            <option value="{{ $tranche }}">{{ $tranche }}</option>
                        @endforeach
                    </select>
                    @error('learner_count')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                {{-- Besoin principal --}}
                <div>
                    <label for="demo_main_need" class="block text-sm font-medium text-slate-700">Besoin principal</label>
                    <select id="demo_main_need" wire:model="main_need"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— Sélectionner —</option>
                        @foreach (\App\Livewire\DemandeDemoForm::BESOINS as $besoin)
                            <option value="{{ $besoin }}">{{ $besoin }}</option>
                        @endforeach
                    </select>
                    @error('main_need')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Message --}}
            <div>
                <label for="demo_message" class="block text-sm font-medium text-slate-700">Message</label>
                <textarea id="demo_message" wire:model.blur="message" rows="3" placeholder="Décrivez brièvement votre organisation et vos besoins…"
                    class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('message') border-rose-400 @else border-slate-300 @enderror"></textarea>
                @error('message')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            {{-- Consentement --}}
            <div>
                <label class="flex items-start gap-2.5 text-xs text-slate-500">
                    <input type="checkbox" wire:model="consent" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span>J'accepte que mes informations soient utilisées pour être recontacté(e). Consultez notre <a href="{{ route('vitrine.confidentialite') }}" class="font-medium text-indigo-600 underline">politique de confidentialité</a>.</span>
                </label>
                @error('consent')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="envoyer"
                class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="envoyer">Demander une démonstration</span>
                <span wire:loading wire:target="envoyer">Envoi en cours…</span>
            </button>
            <p class="text-center text-xs text-slate-400">Réponse sous 48 h ouvrées · Sans engagement</p>
        </form>
    @endif
</div>