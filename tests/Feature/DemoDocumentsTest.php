<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\DocumentType;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organisation;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Qualité des documents de démonstration.
 *
 * Panne silencieuse constatée le 2026-07-15 : 183 documents en base, AUCUN avec
 * un fichier. Chaque pièce s'annonçait « reçue » et ne s'ouvrait pas — on
 * cliquait dans le vide. Rien ne cassait, tout mentait.
 */
beforeEach(function () {
    Filament::setTenant(null, isQuiet: true);
    $this->seed(DatabaseSeeder::class);
    Filament::setTenant(Organisation::where('slug', 'cfa-v2s')->sole(), isQuiet: true);
});

it('attache un vrai fichier à chaque document de démonstration', function () {
    $total = Document::query()->count();
    $sansFichier = Document::query()->doesntHave('media')->count();

    expect($total)->toBeGreaterThan(0)
        ->and($sansFichier)->toBe(0);
});

it('ne prétend pas qu’un contrat est signé sans contrat signé au dossier', function () {
    $signes = Contract::query()
        ->where('statut_signature', ContractSignatureStatut::Signe->value)
        ->get();

    expect($signes)->not->toBeEmpty();

    // L'application interdit de marquer un contrat signé sans preuve : les
    // données de démo doivent respecter la même règle.
    $sansPreuve = $signes->filter(
        fn (Contract $c): bool => ! $c->documents()->where('type', DocumentType::Contrat->value)->exists()
    );

    expect($sansPreuve->pluck('id')->all())->toBe([]);
});

it('rend les pièces du candidat réellement ouvrables', function () {
    // Le cas signalé : on ouvre la fiche d'un apprenti, la pièce est listée…
    // et il n'y a rien à ouvrir.
    $piece = Document::query()
        ->where('type', DocumentType::PieceIdentite->value)
        ->first();

    expect($piece)->not->toBeNull()
        ->and($piece->getFirstMedia('fichier'))->not->toBeNull();
});
