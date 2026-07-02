<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Support\RawJs;

/**
 * Rend les segments d'un ChartWidget cliquables (P0-12-4) : un clic sur un
 * point/barre/part redirige vers la liste filtrée correspondante.
 *
 * Le widget fournit les URLs alignées sur l'ordre des labels via
 * {@see getSegmentUrls()} et retourne {@see clickableOptions()} depuis getOptions().
 */
trait HasClickableChart
{
    /**
     * URLs cibles, dans le même ordre que les labels du graphique.
     * Une entrée null/'' rend le segment non cliquable.
     *
     * @return array<int, string|null>
     */
    abstract protected function getSegmentUrls(): array;

    /**
     * Fusionne les options Chart.js avec un handler de clic et un curseur
     * « pointer » au survol des segments cliquables.
     *
     * @param  array<string, mixed>  $options
     */
    protected function clickableOptions(array $options = []): RawJs
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $optionsJson = json_encode($options, $flags) ?: '{}';
        $urlsJson = json_encode(array_values($this->getSegmentUrls()), $flags) ?: '[]';

        return RawJs::make(<<<JS
            (() => {
                const options = {$optionsJson};
                const urls = {$urlsJson};
                options.onClick = (event, elements) => {
                    if (! elements.length) return;
                    const url = urls[elements[0].index];
                    if (url) window.location.href = url;
                };
                options.onHover = (event, elements) => {
                    const target = event?.native?.target;
                    if (target) {
                        target.style.cursor = elements.length && urls[elements[0].index]
                            ? 'pointer'
                            : 'default';
                    }
                };
                return options;
            })()
            JS);
    }
}
