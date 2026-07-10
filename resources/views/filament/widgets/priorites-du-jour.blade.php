<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Priorités du jour</x-slot>
        <x-slot name="description">
            {{ now()->translatedFormat('l j F Y') }} — ce qui demande votre attention maintenant.
        </x-slot>

        @php
            $icones = [
                'document-minus' => 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'pencil-square' => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125',
                'exclamation-triangle' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                'banknotes' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
                'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'check-circle' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            ];
        @endphp

        @if (count($priorites) === 0)
            <div class="cfa-prio">
                <div class="cfa-prio-card cfa-prio-ok">
                    <span class="cfa-prio-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icones['check-circle'] }}" /></svg>
                    </span>
                    <span>
                        <span class="cfa-prio-label">Tout est à jour ✨</span>
                        <span class="cfa-prio-hint">Aucun dossier bloqué, aucune échéance urgente. Belle journée !</span>
                    </span>
                </div>
            </div>
        @else
            <div class="cfa-prio">
                @foreach ($priorites as $p)
                    @php $tag = $p['url'] ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if($p['url']) href="{{ $p['url'] }}" @endif class="cfa-prio-card cfa-prio-{{ $p['tone'] }}">
                        <span class="cfa-prio-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icones[$p['icon']] ?? $icones['clock'] }}" /></svg>
                        </span>
                        <span>
                            <span class="cfa-prio-count">{{ $p['count'] }}</span>
                            <span class="cfa-prio-label"> {{ $p['label'] }}</span>
                            <span class="cfa-prio-hint">{{ $p['hint'] }}</span>
                        </span>
                    </{{ $tag }}>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
