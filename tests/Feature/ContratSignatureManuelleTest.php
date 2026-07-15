<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\DocumentType;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Mail\DocumentsASigner;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Circuit de signature manuel : on envoie le CERFA + la convention par mail, la
 * partie imprime, signe, scanne et renvoie ; on dépose le scan, le contrat est
 * signé.
 *
 * Retenu à la place d'un prestataire de signature électronique, dont
 * l'abonnement API (~1 250 €/an) ne se justifie pas au volume du CFA.
 */
function adminContrats(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

/** Contrat réellement envoyable : le CERFA et la convention exigent l'un et l'autre. */
function contratEnvoyable(ContractSignatureStatut $statut = ContractSignatureStatut::NonSigne): Contract
{
    // Une convention sans SIRET ni représentant du CFA n'est pas valable :
    // l'action bloque tant que les paramètres du CFA ne sont pas renseignés.
    Organisation::courante()->update([
        'raison_sociale' => 'CFA V2S',
        'nom' => 'CFA V2S',
        'siret' => '11111111100011',
        'representant_nom' => 'Durand',
        'representant_prenom' => 'Claire',
        'representant_fonction' => 'Directrice',
        'adresse' => '5 avenue de la République',
        'ville' => 'Paris',
    ]);

    $company = Company::factory()->create();
    $tuteur = CompanyContact::factory()->create([
        'company_id' => $company->id,
        'is_tuteur' => true,
    ]);

    return Contract::factory()->create([
        'statut_signature' => $statut,
        'salaire_mensuel_brut' => 977.55,
        'company_id' => $company->id,
        // Le CERFA exige un maître d'apprentissage : sans lui, l'envoi est bloqué.
        'tuteur_id' => $tuteur->id,
    ]);
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminContrats());
    Mail::fake();
    Storage::fake(config('media-library.disk_name', 'public'));
});

it('envoie le CERFA et la convention aux parties, et passe le contrat à « Envoyé »', function () {
    $contract = contratEnvoyable();
    $apprenti = $contract->candidate->email;
    $tuteur = $contract->tuteur->email;

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('envoyerDocumentsASigner', [
            'destinataires' => [$apprenti, $tuteur],
            'message' => 'Merci de nous retourner les documents avant le 30.',
        ]);

    Mail::assertSent(DocumentsASigner::class, 2);
    Mail::assertSent(DocumentsASigner::class, fn (DocumentsASigner $m): bool => $m->hasTo($apprenti));
    Mail::assertSent(DocumentsASigner::class, fn (DocumentsASigner $m): bool => $m->hasTo($tuteur));

    expect($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::Envoye);
});

it('pré-remplit les destinataires depuis le dossier', function () {
    $contract = contratEnvoyable();

    // Personne ne doit avoir à retrouver l'adresse de l'apprenti à la main :
    // l'ERP les connaît déjà.
    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->mountAction('envoyerDocumentsASigner')
        ->assertActionDataSet(fn (array $data): bool => in_array($contract->candidate->email, $data['destinataires'], true)
            && in_array($contract->tuteur->email, $data['destinataires'], true));
});

it('joint réellement les deux PDF au message', function () {
    $contract = contratEnvoyable();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('envoyerDocumentsASigner', ['destinataires' => [$contract->candidate->email]]);

    // Le destinataire n'a ni compte à créer ni lien à suivre : tout est en pièce
    // jointe. Sans cela, le circuit ne tient pas.
    Mail::assertSent(DocumentsASigner::class, function (DocumentsASigner $m): bool {
        $noms = array_keys($m->pieces);

        return count($noms) === 2
            && collect($noms)->contains(fn (string $n): bool => str_starts_with($n, 'CERFA_'))
            && collect($noms)->contains(fn (string $n): bool => str_starts_with($n, 'Convention_'));
    });
});

it('archive en GED ce qui a été envoyé', function () {
    $contract = contratEnvoyable();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('envoyerDocumentsASigner', ['destinataires' => [$contract->candidate->email]]);

    // On doit pouvoir prouver ce qui est parti, exactement.
    // NB : pluck() applique le cast — on récupère des DocumentType, pas des chaînes.
    $types = $contract->documents()->pluck('type');

    expect($types)->toContain(DocumentType::Cerfa)
        ->and($types)->toContain(DocumentType::Convention);
});

it('refuse d’envoyer un contrat incomplet', function () {
    // Un CERFA troué envoyé à un employeur, c'est tout le circuit à recommencer :
    // impression, signature, scan, retour. Mieux vaut bloquer à l'envoi.
    $contract = contratEnvoyable();
    $contract->update(['salaire_mensuel_brut' => null]);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('envoyerDocumentsASigner', ['destinataires' => [$contract->candidate->email]]);

    Mail::assertNothingSent();

    expect($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::NonSigne);
});

it('refuse aussi tant que les paramètres du CFA sont incomplets', function () {
    // Cas piégeux : le contrat est parfait, mais une convention sans SIRET du CFA
    // n'est pas valable. Le message doit envoyer l'utilisateur au bon endroit —
    // il chercherait sinon le « SIRET du CFA » dans la fiche contrat.
    $contract = contratEnvoyable();
    Organisation::courante()->update(['siret' => null]);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('envoyerDocumentsASigner', ['destinataires' => [$contract->candidate->email]]);

    Mail::assertNothingSent();

    expect($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::NonSigne);
});

it('dépose les documents signés et clôt le contrat', function () {
    $contract = contratEnvoyable(ContractSignatureStatut::Envoye);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('deposerDocumentsSignes', [
            'fichiers' => [UploadedFile::fake()->create('contrat-signe.pdf', 120, 'application/pdf')],
        ]);

    expect($contract->fresh()->statut_signature)->toBe(ContractSignatureStatut::Signe)
        ->and($contract->documents()->where('type', DocumentType::Contrat->value)->exists())->toBeTrue();
});

it('n’offre plus d’envoyer ni de déposer un contrat déjà signé', function () {
    $contract = contratEnvoyable(ContractSignatureStatut::Signe);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->assertActionHidden('envoyerDocumentsASigner')
        ->assertActionHidden('deposerDocumentsSignes');
});
