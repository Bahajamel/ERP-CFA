<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <input
        type="file"
        id="{{ $name }}"
        name="{{ $name }}"
        class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-600 shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 @error($name) border-rose-400 @else border-slate-300 @enderror"
    >
    @if (! empty($hint))<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
