<?php

use App\Enums\DocumentType;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('crée une classe/promotion reliée à une formation', function () {
    $promotion = Promotion::factory()->create();

    expect($promotion->exists)->toBeTrue()
        ->and($promotion->formation)->toBeInstanceOf(Formation::class);
});

it('rattache des apprentis à une classe', function () {
    $promotion = Promotion::factory()->create();
    Candidate::factory()->count(3)->create(['promotion_id' => $promotion->id]);

    expect($promotion->apprentis()->count())->toBe(3)
        ->and(Candidate::first()->promotion->is($promotion))->toBeTrue();
});

it('réserve la gestion des classes aux rôles du référentiel', function () {
    $scolarite = User::factory()->create();
    $scolarite->syncRoles(['Scolarité']);
    $this->actingAs($scolarite);
    expect(PromotionResource::canAccess())->toBeTrue();

    $finance = User::factory()->create();
    $finance->syncRoles(['Finance']);
    $this->actingAs($finance);
    expect(PromotionResource::canAccess())->toBeFalse();
});

it('utilise une checklist d\'admission réaliste (CFA)', function () {
    expect(Admission::PIECES_OBLIGATOIRES)->toContain(DocumentType::PieceIdentite)
        ->and(Admission::PIECES_OBLIGATOIRES)->toContain(DocumentType::CvCandidat)
        ->and(Admission::PIECES_OBLIGATOIRES)->toContain(DocumentType::DiplomeBulletins)
        // Le CV du maître d'apprentissage ne concerne PAS l'admission.
        ->and(Admission::PIECES_OBLIGATOIRES)->not->toContain(DocumentType::CvMaitreApprentissage);
});
