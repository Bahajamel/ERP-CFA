@extends('public.layout')

@php $theme = \App\Support\BoardNavigation::themePublic($table->context); @endphp

@section('title', $table->name)
@section('heroImage', asset($theme['image']))
@section('heroGradient', $theme['gradient'])
@section('badge', $theme['badge'])
@section('footer', $theme['footer'])
@section('heading', $table->name)
@section('subheading', $table->description)

@section('content')
    <p class="mb-6 text-sm text-slate-500">
        Les champs marqués <span class="font-semibold text-rose-500">*</span> sont obligatoires.
    </p>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-rose-50 p-4 text-sm text-rose-700 ring-1 ring-rose-600/10">
            <p class="font-semibold">Merci de corriger les points suivants :</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tableau.candidature.store', ['token' => $table->public_token]) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Honeypot anti-bot (invisible). --}}
        <div class="hidden" aria-hidden="true">
            <label>Ne rien saisir ici <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($colonnes as $def)
                @php $nom = "champs[{$def->key}]"; $cle = "champs.{$def->key}"; @endphp

                @if ($def->type->value === 'boolean')
                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200 sm:col-span-2">
                        <input type="checkbox" name="{{ $nom }}" value="1" @checked(old($cle))
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">
                            {{ $def->label }}@if ($def->is_required) <span class="font-semibold text-rose-500">*</span>@endif
                        </span>
                    </label>
                @else
                    <div @class(['sm:col-span-2' => $def->type->value === 'textarea'])>
                        <label for="{{ $def->key }}" class="block text-sm font-medium text-slate-700">
                            {{ $def->label }}@if ($def->is_required) <span class="font-semibold text-rose-500">*</span>@endif
                        </label>

                        @switch($def->type->value)
                            @case('textarea')
                                <textarea id="{{ $def->key }}" name="{{ $nom }}" rows="3"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old($cle) }}</textarea>
                                @break
                            @case('number')
                                <input type="number" step="any" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('date')
                                <input type="date" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('time')
                                <input type="time" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('email')
                                <input type="email" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}" maxlength="255"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('phone')
                                <input type="tel" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}" maxlength="30"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('url')
                                <input type="url" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}" maxlength="500" placeholder="https://…"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('amount')
                                <input type="number" step="0.01" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}" placeholder="€"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('percent')
                                <input type="number" step="any" min="0" max="100" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @break
                            @case('file')
                                <input type="file" id="{{ $def->key }}" name="{{ $nom }}"
                                    accept=".pdf,.doc,.docx,.odt,.jpg,.jpeg,.png,.webp"
                                    class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-slate-400">PDF, Word, image — 10 Mo maximum.</p>
                                @break
                            @case('select')
                            @case('statut')
                                <select id="{{ $def->key }}" name="{{ $nom }}"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Choisir —</option>
                                    @foreach (($def->config['options'] ?? []) as $opt)
                                        <option value="{{ $opt }}" @selected(old($cle) === $opt)>{{ $opt }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('multiselect')
                                @php $choisies = (array) old($cle, []); @endphp
                                <div class="mt-1 grid gap-1.5 sm:grid-cols-2">
                                    @foreach (($def->config['options'] ?? []) as $opt)
                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="champs[{{ $def->key }}][]" value="{{ $opt }}"
                                                @checked(in_array($opt, $choisies))
                                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ $opt }}
                                        </label>
                                    @endforeach
                                </div>
                                @break
                            @default
                                <input type="text" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}" maxlength="255"
                                    class="mt-1 w-full rounded-lg border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @endswitch

                        @error($cle)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
            @endforeach
        </div>

        @if ($colonnes->isEmpty())
            <p class="text-sm text-slate-500">Ce formulaire n'a pas encore de champ. Revenez plus tard.</p>
        @else
            <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ \App\Support\BoardNavigation::boutonEnvoi($table->context) }}
            </button>
        @endif
    </form>
@endsection
