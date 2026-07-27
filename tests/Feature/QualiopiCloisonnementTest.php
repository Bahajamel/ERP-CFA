<?php

use App\Enums\QualiopiStatut;
use App\Models\Organisation;
use App\Models\QualiopiIndicator;
use App\Provisioning\ProvisionnerCfaEssai;
use App\Qualiopi\ReferentielQualiopi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisionne 32 indicateurs pour une organisation', function () {
    $organisation = Organisation::factory()->create();

    ReferentielQualiopi::provisionner($organisation->id);

    $indicateurs = QualiopiIndicator::withoutGlobalScopes()->where('organisation_id', $organisation->id);

    expect($indicateurs->count())->toBe(32)
        ->and((clone $indicateurs)->distinct('critere')->count('critere'))->toBe(7)
        ->and((clone $indicateurs)->where('specifique_cfa', true)->count())->toBe(6);
});

it('est idempotent et n\'écrase pas l\'état de conformité déjà saisi', function () {
    $organisation = Organisation::factory()->create();
    ReferentielQualiopi::provisionner($organisation->id);

    // Un responsable qualifie l'indicateur n°1.
    QualiopiIndicator::withoutGlobalScopes()
        ->where('organisation_id', $organisation->id)->where('numero', 1)
        ->update(['statut' => QualiopiStatut::Conforme->value, 'commentaire' => 'Preuve déposée']);

    // Re-provisionner (ex. re-seed) ne recrée pas de doublons ni n'efface l'état.
    ReferentielQualiopi::provisionner($organisation->id);

    $indic = QualiopiIndicator::withoutGlobalScopes()
        ->where('organisation_id', $organisation->id)->where('numero', 1)->first();

    expect(QualiopiIndicator::withoutGlobalScopes()->where('organisation_id', $organisation->id)->count())->toBe(32)
        ->and($indic->statut)->toBe(QualiopiStatut::Conforme)
        ->and($indic->commentaire)->toBe('Preuve déposée');
});

it('cloisonne l\'état de conformité entre deux CFA', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();
    ReferentielQualiopi::provisionner($cfaA->id);
    ReferentielQualiopi::provisionner($cfaB->id);

    // Le CFA A déclare son indicateur n°1 conforme.
    QualiopiIndicator::withoutGlobalScopes()
        ->where('organisation_id', $cfaA->id)->where('numero', 1)
        ->update(['statut' => QualiopiStatut::Conforme->value]);

    $chezA = QualiopiIndicator::withoutGlobalScopes()->where('organisation_id', $cfaA->id)->where('numero', 1)->first();
    $chezB = QualiopiIndicator::withoutGlobalScopes()->where('organisation_id', $cfaB->id)->where('numero', 1)->first();

    // L'état du CFA A ne déteint pas sur le CFA B — c'est tout l'enjeu.
    expect($chezA->statut)->toBe(QualiopiStatut::Conforme)
        ->and($chezB->statut)->not->toBe(QualiopiStatut::Conforme)
        // Deux lignes distinctes, une par CFA.
        ->and($chezA->id)->not->toBe($chezB->id);
});

it('donne ses 32 indicateurs à un CFA ouvert en essai', function () {
    $this->seed(RolePermissionSeeder::class);

    $r = app(ProvisionnerCfaEssai::class)->creer('CFA Nouveau', 'admin@cfa-nouveau.test');

    expect(QualiopiIndicator::withoutGlobalScopes()->where('organisation_id', $r['organisation']->id)->count())->toBe(32);
});
