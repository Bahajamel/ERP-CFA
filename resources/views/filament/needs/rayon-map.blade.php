@php
    $hasPoint = filled($lat) && filled($lon);
    $rayonKm = (float) ($rayonKm ?? 0);
    $rayonM = (int) round($rayonKm * 1000);
@endphp

@if ($hasPoint)
    {{-- La clé change avec le point/rayon → Livewire remplace le bloc et Alpine
         reconstruit la carte (recentrage + nouveau cercle). Le conteneur interne
         porte wire:ignore pour protéger le DOM injecté par Leaflet. --}}
    <div
        wire:key="need-map-{{ $lat }}-{{ $lon }}-{{ $rayonM }}"
        x-data="{
            map: null,
            build() {
                if (! window.L) { setTimeout(() => this.build(), 120); return; }
                const lat = {{ $lat }}, lon = {{ $lon }}, r = {{ $rayonM }};
                this.map = window.L.map(this.$refs.map).setView([lat, lon], 12);
                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 19,
                }).addTo(this.map);
                window.L.marker([lat, lon]).addTo(this.map);
                if (r > 0) {
                    const circle = window.L.circle([lat, lon], {
                        radius: r, color: '#2563eb', weight: 2,
                        fillColor: '#3b82f6', fillOpacity: 0.15,
                    }).addTo(this.map);
                    this.map.fitBounds(circle.getBounds(), { padding: [20, 20] });
                }
                setTimeout(() => this.map && this.map.invalidateSize(), 200);
            },
            init() {
                if (! document.getElementById('leaflet-css')) {
                    const link = document.createElement('link');
                    link.id = 'leaflet-css';
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(link);
                }
                if (! window.L && ! document.getElementById('leaflet-js')) {
                    const script = document.createElement('script');
                    script.id = 'leaflet-js';
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    document.head.appendChild(script);
                }
                this.build();
            },
        }"
    >
        <div
            x-ref="map"
            wire:ignore
            style="height: 320px; border-radius: 8px; overflow: hidden; z-index: 0;"
        ></div>
        <p style="margin-top: .5rem; font-size: .8rem; color: #6b7280;">
            Rayon de recherche : <strong>{{ $rayonKm > 0 ? rtrim(rtrim(number_format($rayonKm, 1, ',', ' '), '0'), ',').' km' : 'non défini' }}</strong>
            autour du point géolocalisé.
        </p>
    </div>
@else
    <div style="padding: 1rem; border: 1px dashed #d1d5db; border-radius: 8px; background: rgba(148,163,184,.08);">
        <p style="font-size: .875rem; color: #6b7280; margin: 0;">
            🗺️ Aucune adresse géolocalisée : renseignez une adresse via la recherche
            ci-dessus (ou saisissez latitude et longitude) pour afficher le rayon de
            recherche sur la carte.
        </p>
    </div>
@endif
