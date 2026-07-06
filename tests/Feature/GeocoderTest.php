<?php

use App\Prospecting\AddressGeocoder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.geocoder', [
        'base_url' => 'https://adresse.test',
        'verify_ssl' => true,
    ]);
});

it('géocode une ville en coordonnées lat/lon', function () {
    Http::fake(['adresse.test/*' => Http::response([
        'features' => [[
            'geometry' => ['coordinates' => [4.8357, 45.7640]], // [lon, lat] Lyon
            'properties' => ['label' => 'Lyon'],
        ]],
    ])]);

    $geo = app(AddressGeocoder::class)->geocode('Lyon');

    expect($geo)->not->toBeNull()
        ->and($geo['lat'])->toBe(45.7640)
        ->and($geo['lon'])->toBe(4.8357)
        ->and($geo['label'])->toBe('Lyon');
});

it('retourne null quand aucun lieu ne correspond', function () {
    Http::fake(['adresse.test/*' => Http::response(['features' => []])]);

    expect(app(AddressGeocoder::class)->geocode('zzzzzz'))->toBeNull();
});
