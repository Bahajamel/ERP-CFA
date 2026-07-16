@php($required = $required ?? false)
@php($placeholder = $placeholder ?? null)
<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        value="{{ old($name) }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error($name) border-rose-400 @else border-slate-300 @enderror"
    >
    @error($name)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
