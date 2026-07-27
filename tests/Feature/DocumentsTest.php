<?php

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeUserAvecRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles([$role]);

    return $user;
}

it('réserve la GED aux rôles disposant de access_documents', function () {
    $this->actingAs(makeUserAvecRole('Qualité'));
    expect(DocumentResource::canAccess())->toBeTrue();

    $this->actingAs(makeUserAvecRole('Administratif'));
    expect(DocumentResource::canAccess())->toBeTrue();

    $this->actingAs(makeUserAvecRole('Commercial'));
    expect(DocumentResource::canAccess())->toBeFalse();
});

it('crée une nouvelle version chaînée et bascule la version courante', function () {
    $v1 = Document::factory()->create(['version' => 1, 'type' => DocumentType::Autre]);

    $v2 = $v1->creerNouvelleVersion(['statut' => DocumentStatut::Recu]);

    expect($v2->version)->toBe(2)
        ->and($v2->previous_version_id)->toBe($v1->id)
        ->and($v2->documentable_id)->toBe($v1->documentable_id)
        ->and($v1->fresh()->estCourante())->toBeFalse()
        ->and($v2->estCourante())->toBeTrue();
});

it('ne liste que les versions courantes via le scope', function () {
    $v1 = Document::factory()->create(['version' => 1, 'type' => DocumentType::Autre]);
    $v2 = $v1->creerNouvelleVersion();

    $courantes = Document::versionsCourantes()->pluck('id');

    expect($courantes)->toContain($v2->id)
        ->and($courantes)->not->toContain($v1->id);
});

it('reconstitue l\'historique complet des versions', function () {
    $v1 = Document::factory()->create(['version' => 1, 'type' => DocumentType::Autre]);
    $v2 = $v1->creerNouvelleVersion();
    $v3 = $v2->creerNouvelleVersion();

    $ids = collect($v3->historiqueVersions())->pluck('id')->all();

    expect($ids)->toBe([$v3->id, $v2->id, $v1->id]);
});

it('interdit la suppression définitive d\'un document critique', function () {
    $contrat = Document::factory()->create(['type' => DocumentType::Contrat]);

    expect(fn () => $contrat->forceDelete())->toThrow(RuntimeException::class);
    expect(Document::withTrashed()->find($contrat->id))->not->toBeNull();
});

it('autorise l\'archivage (soft delete) d\'un document critique', function () {
    $contrat = Document::factory()->create(['type' => DocumentType::Contrat]);

    $contrat->delete();

    expect($contrat->trashed())->toBeTrue();
});

it('autorise la suppression définitive d\'un document non critique', function () {
    $autre = Document::factory()->create(['type' => DocumentType::Autre]);

    $autre->forceDelete();

    expect(Document::withTrashed()->find($autre->id))->toBeNull();
});

it('attache un fichier au document via medialibrary', function () {
    Storage::fake('public');

    $document = Document::factory()->create();
    $document->addMediaFromString('contenu de test')
        ->usingFileName('dossier.pdf')
        ->toMediaCollection('fichier');

    expect($document->getFirstMedia('fichier'))->not->toBeNull()
        ->and($document->getFirstMedia('fichier')->file_name)->toBe('dossier.pdf');
});
