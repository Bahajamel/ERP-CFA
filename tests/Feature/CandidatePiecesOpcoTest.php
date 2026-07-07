<?php

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Models\Candidate;
use App\Models\Opco;
use App\Models\User;
use App\Support\OpcoDetector;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function piecesUser(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

/*
|--------------------------------------------------------------------------
| Pièces justificatives candidat
|--------------------------------------------------------------------------
*/

it('attache et détecte les pièces justificatives du candidat', function () {
    Storage::fake('public');
    $candidate = Candidate::factory()->create();

    $candidate->addMediaFromString("%PDF-1.4\n%%EOF")->usingFileName('cni.pdf')->toMediaCollection('piece_identite');
    $candidate->addMediaFromString("%PDF-1.4\n%%EOF")->usingFileName('vitale.pdf')->toMediaCollection('carte_vitale');
    $candidate->addMediaFromString("%PDF-1.4\n%%EOF")->usingFileName('projet.pdf')->toMediaCollection('attestation_projet');

    $fresh = $candidate->fresh();

    expect($fresh->getFirstMedia('piece_identite'))->not->toBeNull()
        ->and($fresh->getFirstMedia('carte_vitale'))->not->toBeNull()
        ->and($fresh->getFirstMedia('attestation_projet'))->not->toBeNull()
        // Chaque pièce vit dans sa collection : le CV reste indépendant.
        ->and($fresh->hasCv())->toBeFalse();
});

it('calcule la règle des plus de 30 ans depuis la date de naissance', function () {
    expect(Candidate::dateNaissancePlusDe30Ans(now()->subYears(31)->format('Y-m-d')))->toBeTrue()
        ->and(Candidate::dateNaissancePlusDe30Ans(now()->subYears(25)->format('Y-m-d')))->toBeFalse()
        ->and(Candidate::dateNaissancePlusDe30Ans(null))->toBeFalse()
        ->and(Candidate::dateNaissancePlusDe30Ans('pas-une-date'))->toBeFalse();
});

it('exige l\'attestation de création de projet pour un candidat de plus de 30 ans', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(piecesUser());
    Storage::fake('public');

    Livewire::test(CreateCandidate::class)
        ->fillForm([
            'nom' => 'Durand',
            'prenom' => 'Paul',
            'email' => 'paul.durand@exemple.fr',
            'date_naissance' => now()->subYears(35)->format('Y-m-d'),
        ])
        ->call('create')
        ->assertHasFormErrors(['attestation_projet']);

    expect(Candidate::query()->where('email', 'paul.durand@exemple.fr')->exists())->toBeFalse();
});

it('crée un candidat de 30 ans ou moins sans attestation de projet', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(piecesUser());
    Storage::fake('public');

    Livewire::test(CreateCandidate::class)
        ->fillForm([
            'nom' => 'Petit',
            'prenom' => 'Léa',
            'email' => 'lea.petit@exemple.fr',
            'date_naissance' => now()->subYears(22)->format('Y-m-d'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Candidate::query()->where('email', 'lea.petit@exemple.fr')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Détection OPCO par SIRET (CFA Dock)
|--------------------------------------------------------------------------
*/

it('normalise et valide le SIRET', function () {
    expect(OpcoDetector::normaliserSiret('123 456 789 00012'))->toBe('12345678900012')
        ->and(OpcoDetector::siretValide('123 456 789 00012'))->toBeTrue()
        ->and(OpcoDetector::siretValide('12345'))->toBeFalse()
        ->and(OpcoDetector::siretValide(null))->toBeFalse();
});

it('détecte l\'OPCO via CFA Dock et le rattache au référentiel existant', function () {
    Opco::query()->create(['nom' => 'AKTO']);

    Http::fake([
        'www.cfadock.fr/*' => Http::response(['searchStatus' => 'OK', 'opcoName' => 'Akto']),
    ]);

    $resultat = app(OpcoDetector::class)->detecter('123 456 789 00012');

    expect($resultat['statut'])->toBe('ok')
        ->and($resultat['opco']->nom)->toBe('AKTO')
        // Correspondance insensible à la casse : pas de doublon créé.
        ->and(Opco::query()->count())->toBe(1);
});

it('crée l\'OPCO au référentiel s\'il est inconnu', function () {
    Http::fake([
        'www.cfadock.fr/*' => Http::response(['searchStatus' => 'OK', 'opcoName' => 'OPCO Santé']),
    ]);

    $resultat = app(OpcoDetector::class)->detecter('12345678900012');

    expect($resultat['statut'])->toBe('ok')
        ->and(Opco::query()->where('nom', 'OPCO Santé')->exists())->toBeTrue();
});

it('signale un OPCO introuvable sans bloquer', function () {
    Http::fake([
        'www.cfadock.fr/*' => Http::response(['searchStatus' => 'NOT_FOUND']),
    ]);

    expect(app(OpcoDetector::class)->detecter('12345678900012')['statut'])->toBe('introuvable');
});

it('ne lance pas de détection sur un SIRET invalide', function () {
    Http::fake();

    $resultat = app(OpcoDetector::class)->detecter('123');

    expect($resultat['statut'])->toBe('introuvable');
    Http::assertNothingSent();
});
