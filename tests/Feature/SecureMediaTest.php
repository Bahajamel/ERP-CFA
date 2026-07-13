<?php

use App\Models\Candidate;
use App\Models\User;
use App\Support\SecureMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Les pièces sensibles vivent sur le disque privé ; le disque public web-exposé
    // ne doit jamais recevoir de donnée sensible.
    Storage::fake('local');
    Storage::fake('public');
});

/** Un candidat doté d'une carte vitale (collection sensible). */
function candidatAvecCarteVitale(): Candidate
{
    $candidat = Candidate::factory()->create();
    $candidat
        ->addMedia(UploadedFile::fake()->createWithContent('vitale.pdf', "%PDF-1.4\nfaux\n%%EOF"))
        ->toMediaCollection('carte_vitale');

    return $candidat;
}

it('stocke les pièces sensibles sur le disque privé, jamais sur le public', function () {
    $media = candidatAvecCarteVitale()->getFirstMedia('carte_vitale');

    expect($media->disk)->toBe('local');
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('refuse l’accès à une pièce sensible sans authentification', function () {
    $url = SecureMedia::url(candidatAvecCarteVitale()->getFirstMedia('carte_vitale'));

    $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
    $this->assertGuest();
});

it('refuse une URL non signée même à un utilisateur authentifié', function () {
    $media = candidatAvecCarteVitale()->getFirstMedia('carte_vitale');

    $this->actingAs(User::factory()->create())
        ->get(route('documents.securise', ['media' => $media->getKey()]))
        ->assertForbidden();
});

it('sert la pièce via un lien signé valide à un utilisateur authentifié', function () {
    $url = SecureMedia::url(candidatAvecCarteVitale()->getFirstMedia('carte_vitale'));

    $response = $this->actingAs(User::factory()->create())->get($url);

    $response->assertOk();
    expect($response->streamedContent())->toContain('%PDF');
});

it('expire le lien signé passé le délai de validité', function () {
    $url = SecureMedia::url(candidatAvecCarteVitale()->getFirstMedia('carte_vitale'));

    $this->travel(config('documents.lien_expiration_minutes') + 1)->minutes();

    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertForbidden();
});
