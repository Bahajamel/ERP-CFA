<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $table->name }} — Candidature</title>
    <style>
        :root { --p: #4f46e5; --ink: #1e293b; --line: #e2e8f0; --muted: #64748b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               background: #f1f5f9; color: var(--ink); }
        .wrap { max-width: 640px; margin: 0 auto; padding: 2.5rem 1.25rem; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 1rem;
                box-shadow: 0 10px 30px -20px rgba(15,23,42,.35); padding: 1.75rem 1.75rem 2rem; }
        h1 { margin: 0 0 .25rem; font-size: 1.5rem; }
        .desc { color: var(--muted); margin: 0 0 1.5rem; }
        .field { margin-bottom: 1.1rem; }
        label { display: block; font-weight: 600; font-size: .92rem; margin-bottom: .4rem; }
        .req { color: #ef4444; }
        input[type=text], input[type=number], input[type=date], textarea, select {
            width: 100%; padding: .6rem .7rem; border: 1px solid var(--line); border-radius: .55rem;
            font-size: 1rem; color: var(--ink); background: #fff;
        }
        input:focus, textarea:focus, select:focus { outline: 2px solid var(--p); outline-offset: 0; border-color: var(--p); }
        textarea { min-height: 5rem; resize: vertical; }
        .check { display: flex; align-items: center; gap: .5rem; }
        .check input { width: 1.1rem; height: 1.1rem; }
        .err { color: #ef4444; font-size: .82rem; margin-top: .3rem; }
        .hp { position: absolute; left: -9999px; }
        button { width: 100%; margin-top: .5rem; padding: .8rem; border: 0; border-radius: .6rem;
                 background: var(--p); color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer; }
        button:hover { background: #4338ca; }
        .foot { text-align: center; color: var(--muted); font-size: .8rem; margin-top: 1.25rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ $table->name }}</h1>
            <p class="desc">
                {{ $table->description ?: 'Remplissez ce formulaire, votre candidature sera transmise directement.' }}
            </p>

            @if ($errors->any())
                <div class="err" style="margin-bottom:1rem">Merci de corriger les champs signalés ci-dessous.</div>
            @endif

            <form method="POST" action="{{ route('tableau.candidature.store', ['token' => $table->public_token]) }}">
                @csrf

                {{-- Honeypot anti-bot (invisible). --}}
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">

                @foreach ($colonnes as $def)
                    @php $nom = "champs[{$def->key}]"; $cle = "champs.{$def->key}"; @endphp
                    <div class="field">
                        @if ($def->type->value === 'boolean')
                            <label class="check">
                                <input type="checkbox" name="{{ $nom }}" value="1" @checked(old($cle))>
                                <span>{{ $def->label }} @if ($def->is_required)<span class="req">*</span>@endif</span>
                            </label>
                        @else
                            <label for="{{ $def->key }}">
                                {{ $def->label }} @if ($def->is_required)<span class="req">*</span>@endif
                            </label>

                            @switch($def->type->value)
                                @case('textarea')
                                    <textarea id="{{ $def->key }}" name="{{ $nom }}">{{ old($cle) }}</textarea>
                                    @break
                                @case('number')
                                    <input type="number" step="any" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}">
                                    @break
                                @case('date')
                                    <input type="date" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}">
                                    @break
                                @case('select')
                                @case('statut')
                                    <select id="{{ $def->key }}" name="{{ $nom }}">
                                        <option value="">— Choisir —</option>
                                        @foreach (($def->config['options'] ?? []) as $opt)
                                            <option value="{{ $opt }}" @selected(old($cle) === $opt)>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @default
                                    <input type="text" id="{{ $def->key }}" name="{{ $nom }}" value="{{ old($cle) }}" maxlength="255">
                            @endswitch
                        @endif

                        @error($cle)
                            <div class="err">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach

                @if ($colonnes->isEmpty())
                    <p class="desc">Ce formulaire n'a pas encore de champ. Revenez plus tard.</p>
                @else
                    <button type="submit">Envoyer ma candidature</button>
                @endif
            </form>
        </div>
        <p class="foot">Vos informations sont transmises uniquement à l'organisme concerné.</p>
    </div>
</body>
</html>
