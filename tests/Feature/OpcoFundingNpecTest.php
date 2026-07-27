<?php

use App\Models\NpecReferentiel;
use App\Services\OpcoFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Détection du NPEC (référentiel France Compétences) et calculs de financement
 * OPCO dérivés. Le référentiel est national (pas de scope CFA).
 */

function serviceOpco(): OpcoFundingService
{
    return app(OpcoFundingService::class);
}

it('trouve le NPEC national par RNCP (IDCC absent)', function () {
    NpecReferentiel::create(['code_rncp' => '37873', 'code_idcc' => null, 'npec_annuel' => 7130, 'source' => 'test']);

    // Le RNCP peut être fourni sous la forme « RNCP37873 » : normalisé en chiffres.
    $npec = serviceOpco()->findNpecByIdccAndRncp(null, 'RNCP37873');

    expect($npec)->not->toBeNull()
        ->and((float) $npec->npec_annuel)->toBe(7130.00);
});

it('privilégie la valeur de branche (IDCC) sur la valeur nationale', function () {
    NpecReferentiel::create(['code_rncp' => '37873', 'code_idcc' => null, 'npec_annuel' => 7130, 'source' => 'test']);
    NpecReferentiel::create(['code_rncp' => '37873', 'code_idcc' => '1486', 'npec_annuel' => 8200, 'source' => 'test']);

    $branche = serviceOpco()->findNpecByIdccAndRncp('1486', 'RNCP37873');
    expect((float) $branche->npec_annuel)->toBe(8200.00);

    // IDCC sans valeur dédiée → repli sur la valeur nationale.
    $repli = serviceOpco()->findNpecByIdccAndRncp('9999', 'RNCP37873');
    expect((float) $repli->npec_annuel)->toBe(7130.00);
});

it('renvoie null si la certification est absente du référentiel', function () {
    expect(serviceOpco()->findNpecByIdccAndRncp('1486', 'RNCP99999'))->toBeNull()
        ->and(serviceOpco()->findNpecByIdccAndRncp(null, null))->toBeNull();
});

it('calcule le NPEC journalier et l\'engagement OPCO total', function () {
    $service = serviceOpco();

    expect($service->calculateDailyNpec(7300))->toBe(20.00) // 7300 / 365
        ->and($service->calculateDailyNpec(0))->toBeNull()
        ->and($service->calculateDailyNpec(null))->toBeNull()
        ->and($service->calculateTotalOpcoFunding(7300, 730))->toBe(14600.00); // 20 × 730
});
