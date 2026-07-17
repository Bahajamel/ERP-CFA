<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Services\CockpitData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Robustesse de l'échéancier de versement (décret n° 2025-585).
 *
 * Constat du 2026-07-15 : les 2 dossiers acceptés de la base (8 000 € et
 * 7 400 €) n'avaient AUCUNE échéance. L'échéancier n'était généré que par la
 * transition vers « Accepté » ; un dossier né accepté (seed, import, reprise
 * de données) ne passait jamais par là. La fonctionnalité était codée, testée,
 * et invisible — l'ancien test appelait genererEcheancier() lui-même, créant la
 * précondition que la réalité ne créait pas.
 */
function contratDe(int $dureeMois = 24): Contract
{
    return Contract::factory()->create([
        'date_debut' => now(),
        'date_fin' => now()->addMonths($dureeMois),
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);
}

it('génère l’échéancier d’un dossier né « Accepté » (import, reprise de données)', function () {
    // Le cas réel : le dossier ne transite pas, il naît accepté.
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::Accepte,
        'montant_accepte' => 8000,
    ]);

    expect($file->payments()->count())->toBe(4)
        ->and(round((float) $file->payments()->sum('montant_prevu'), 2))->toBe(8000.0);
});

it('génère l’échéancier quand le statut passe à « Accepté » hors machine à états', function () {
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::AttenteRetour,
        'montant_accepte' => 6000,
    ]);

    expect($file->payments()->count())->toBe(0);

    // Mise à jour directe (import, correction en masse) : pas de transitionTo().
    $file->update(['statut' => OpcoStatut::Accepte]);

    expect($file->fresh()->payments()->count())->toBeGreaterThan(0);
});

it('n’invente pas d’échéancier tant que l’OPCO n’a pas accepté', function () {
    // Un montant « accepté » saisi sur un dossier encore en attente ne vaut pas
    // acceptation : rien ne doit être planifié.
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::AttenteRetour,
        'montant_accepte' => 9000,
    ]);

    expect($file->payments()->count())->toBe(0);
});

it('refait le plan quand le montant accepté est révisé et que rien n’est versé', function () {
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::Accepte,
        'montant_accepte' => 8000,
    ]);

    // Cas courant : l'OPCO révise sa prise en charge (proratisation, rupture).
    // Un échéancier resté à 8 000 € annoncerait un financement qui n'existe plus.
    $file->update(['montant_accepte' => 5000]);

    expect(round((float) $file->fresh()->payments()->sum('montant_prevu'), 2))->toBe(5000.0);
});

it('ne réécrit jamais un versement déjà encaissé', function () {
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::Accepte,
        'montant_accepte' => 8000,
    ]);

    $premier = $file->payments()->orderBy('ordre')->first();
    $premier->marquerVerse(3200);

    $file->update(['montant_accepte' => 5000]);

    // L'argent reçu est un fait, pas une prévision : on le laisse intact et on
    // signale l'incohérence plutôt que de récrire l'histoire.
    expect($premier->fresh()->statut)->toBe(PaymentStatut::Verse)
        ->and((float) $premier->fresh()->montant_verse)->toBe(3200.0)
        ->and($file->fresh()->echeancierEstPerime())->toBeTrue();
});

it('signale un échéancier périmé, et seulement lui', function () {
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::Accepte,
        'montant_accepte' => 8000,
    ]);

    expect($file->echeancierEstPerime())->toBeFalse();

    $file->payments()->orderBy('ordre')->first()->marquerVerse(3200);
    $file->update(['montant_accepte' => 5000]);

    expect($file->fresh()->echeancierEstPerime())->toBeTrue();
});

it('garde le versement en retard dans le compteur après le passage du cron', function () {
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::AttenteRetour,
        'montant_accepte' => null,
    ]);

    OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'statut' => PaymentStatut::Attendu,
        'date_prevue' => now()->subDays(30),
        'montant_prevu' => 5000,
    ]);

    $avant = app(CockpitData::class)->kpis();
    $versementsAvant = collect($avant)->firstWhere('label', 'Versements en retard');

    // Le cron quotidien reconnaît le retard et bascule le statut. L'alerte doit
    // survivre à cette reconnaissance : sinon elle s'éteint au moment précis où
    // le problème devient officiel.
    $this->artisan('opco:flag-echeances')->assertSuccessful();

    $apres = app(CockpitData::class)->kpis();
    $versementsApres = collect($apres)->firstWhere('label', 'Versements en retard');

    expect($versementsAvant['valeur'])->toBe('1')
        ->and($versementsApres['valeur'])->toBe('1');
});

it('compte un versement en retard qu’il soit « attendu » ou déjà signalé', function () {
    $file = OpcoFile::factory()->create([
        'contract_id' => contratDe()->id,
        'statut' => OpcoStatut::AttenteRetour,
        'montant_accepte' => null,
    ]);

    OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'statut' => PaymentStatut::Attendu,
        'date_prevue' => now()->subDays(10),
    ]);
    OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'ordre' => 2,
        'statut' => PaymentStatut::EnRetard,
        'date_prevue' => now()->subDays(40),
    ]);
    // Versé : jamais en retard, même échu.
    OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'ordre' => 3,
        'statut' => PaymentStatut::Verse,
        'date_prevue' => now()->subDays(60),
    ]);
    // Pas encore échu.
    OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'ordre' => 4,
        'statut' => PaymentStatut::Attendu,
        'date_prevue' => now()->addDays(30),
    ]);

    expect(OpcoPayment::enRetard()->count())->toBe(2);
});
