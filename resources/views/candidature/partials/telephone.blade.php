@php($required = $required ?? false)
@php($indicatif = $indicatif ?? 'indicatif_pays')
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <div class="mt-1 flex gap-2">
        <select
            name="{{ $indicatif }}"
            aria-label="Indicatif pays"
            class="w-44 shrink-0 rounded-lg border-slate-300 bg-white px-2 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >
            @foreach (\App\Support\Indicatifs::liste() as $i)
                <option value="{{ $i['iso'] }}" @selected(old($indicatif, \App\Support\Indicatifs::defaut()) === $i['iso'])>{{ $i['drapeau'] }} {{ $i['pays'] }} ({{ $i['code'] }})</option>
            @endforeach
        </select>
        <input
            type="tel"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name) }}"
            placeholder="6 12 34 56 78"
            @if ($required) required @endif
            class="w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error($name) border-rose-400 @else border-slate-300 @enderror"
        >
    </div>
    <p class="mt-1 text-xs text-slate-500">Choisissez le pays puis saisissez le numéro (indicatif ajouté automatiquement).</p>
    @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
