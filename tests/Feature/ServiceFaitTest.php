<?php

use App\Enums\DocumentType;
use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Candidate;
use App\Models\Promotion;
use App\Models\Seance;
use App\Scolarite\ServiceFaitPreuve;
use App\Scolarite\ServiceFaitValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Crée une séance validée (présences renseignées) pour une promo à une date. */
function seanceValidee(Promotion $promo, string $date): Seance
{
    $seance = Seance::factory()->create([
        'promotion_id' => $promo->id,
        'date' => $date,
        'heure_debut' => '09:00',
        'heure_fin' => '17:00',
    ]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);
    $seance->update(['statut' => SeanceStatut::Validee]);

    return $seance;
}

it('valide le service fait d\'un mois et fige les totaux', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->count(3)->dansClasse($promo)->create();
    seanceValidee($promo, '2026-09-03');
    seanceValidee($promo, '2026-09-10');

    $sf = app(ServiceFaitValidator::class)->valider($promo, 2026, 9);

    expect($sf->nb_seances)->toBe(2)
        ->and($sf->nb_heures)->toBe(16.0)      // 2 × 8 h
        ->and($sf->taux_presence)->toBe(100)
        ->and($sf->validated_at)->not->toBeNull();
});

it('refuse la validation si une séance du mois n\'est pas validée', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->count(2)->dansClasse($promo)->create();
    seanceValidee($promo, '2026-09-03');
    Seance::factory()->create(['promotion_id' => $promo->id, 'date' => '2026-09-12']); // Planifiée

    expect(fn () => app(ServiceFaitValidator::class)->valider($promo, 2026, 9))
        ->toThrow(RuntimeException::class);
});

it('refuse la validation sans séance sur le mois', function () {
    $promo = Promotion::factory()->create();

    expect(fn () => app(ServiceFaitValidator::class)->valider($promo, 2026, 9))
        ->toThrow(RuntimeException::class);
});

it('empêche de valider deux fois le même mois', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create();
    seanceValidee($promo, '2026-09-03');

    app(ServiceFaitValidator::class)->valider($promo, 2026, 9);

    expect(fn () => app(ServiceFaitValidator::class)->valider($promo, 2026, 9))
        ->toThrow(RuntimeException::class);
});

it('génère une preuve PDF de service fait archivée dans la GED', function () {
    Storage::fake('public');

    $promo = Promotion::factory()->create();
    Candidate::factory()->dansClasse($promo)->create();
    seanceValidee($promo, '2026-09-03');
    $sf = app(ServiceFaitValidator::class)->valider($promo, 2026, 9);

    $document = app(ServiceFaitPreuve::class)->generer($sf);

    expect($document->type)->toBe(DocumentType::PreuveServiceFait)
        ->and($document->getFirstMedia('fichier'))->not->toBeNull()
        ->and($sf->documents()->count())->toBe(1);
});
