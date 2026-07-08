<?php

use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dossierAvecMontant(float $montant, int $dureeMois): OpcoFile
{
    $contract = Contract::factory()->create([
        'date_debut' => now(),
        'date_fin' => now()->addMonths($dureeMois),
        'statut_contrat' => \App\Enums\ContractStatut::Signe,
        'statut_signature' => \App\Enums\ContractSignatureStatut::Signe,
    ]);

    return OpcoFile::factory()->create([
        'contract_id' => $contract->id,
        'statut' => OpcoStatut::AttenteRetour,
        'montant_accepte' => $montant,
    ]);
}

it('génère l\'échéancier 40/30/20/10 pour un contrat ≥ 12 mois (décret 2025-585)', function () {
    $file = dossierAvecMontant(8000, 24);

    $file->genererEcheancier();
    $paiements = $file->payments()->orderBy('ordre')->get();

    expect($paiements)->toHaveCount(4)
        ->and($paiements->pluck('pourcentage')->map(fn ($p) => (float) $p)->all())->toBe([40.0, 30.0, 20.0, 10.0])
        ->and(round((float) $paiements->sum('montant_prevu'), 2))->toBe(8000.0)
        ->and((float) $paiements->first()->montant_prevu)->toBe(3200.0);
});

it('génère un échéancier en 2 versements pour un contrat < 12 mois', function () {
    $file = dossierAvecMontant(6000, 6);

    $file->genererEcheancier();

    expect($file->payments()->count())->toBe(2)
        ->and(round((float) $file->payments()->sum('montant_prevu'), 2))->toBe(6000.0);
});

it('ne régénère pas un échéancier existant (idempotent)', function () {
    $file = dossierAvecMontant(8000, 24);

    $file->genererEcheancier();
    $file->genererEcheancier();

    expect($file->payments()->count())->toBe(4);
});

it('génère automatiquement l\'échéancier à l\'acceptation OPCO', function () {
    $file = dossierAvecMontant(5000, 24);

    $file->transitionTo(OpcoStatut::Accepte);

    expect($file->payments()->count())->toBeGreaterThan(0);
});

it('suit les montants versés et le reste à verser', function () {
    $file = dossierAvecMontant(8000, 24);
    $file->genererEcheancier();

    $premier = $file->payments()->orderBy('ordre')->first();
    $premier->marquerVerse();

    expect($file->montantVerse())->toBe(3200.0)
        ->and($file->resteAVerser())->toBe(4800.0)
        ->and($premier->fresh()->statut)->toBe(PaymentStatut::Verse);
});

it('passe les échéances dépassées en retard et crée une relance', function () {
    $file = dossierAvecMontant(8000, 24);
    $paiement = OpcoPayment::factory()->create([
        'opco_file_id' => $file->id,
        'statut' => PaymentStatut::Attendu,
        'date_prevue' => now()->subDays(5),
    ]);

    $this->artisan('opco:flag-echeances')->assertSuccessful();

    expect($paiement->fresh()->statut)->toBe(PaymentStatut::EnRetard)
        ->and($file->tasks()->where('source', 'auto')->count())->toBe(1);
});
