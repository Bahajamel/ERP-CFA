<?php

use App\Cerfa\CerfaApprentissage;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Models\Contract;
use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * L'identité du CFA appartient au CFA.
 *
 * Constat du 2026-07-15 : cfa_profiles n'avait pas d'organisation_id et
 * CfaProfile::current() faisait firstOrCreate([]) — la PREMIÈRE ligne de la
 * table, quel que soit le CFA connecté. Deux CFA sur la plateforme, et le second
 * générait ses CERFA avec le SIRET, le représentant légal et l'image de
 * signature du premier. Un CERFA est déposé à l'OPCO et à l'État : ce n'est pas
 * un chiffre faux sur un écran, c'est un document officiel signé par un autre.
 *
 * Un test gardait même l'anomalie (« expose un profil CFA unique (singleton) »,
 * qui asseyait CfaProfile::count() === 1).
 */
function dansCeCfa(Organisation $cfa, Closure $closure): mixed
{
    $precedent = Filament::getTenant();

    Filament::setTenant($cfa, isQuiet: true);

    try {
        return $closure();
    } finally {
        Filament::setTenant($precedent, isQuiet: true);
    }
}

it('donne à chaque CFA sa propre identité', function () {
    $cfaA = Organisation::factory()->create(['nom' => 'CFA V2S', 'siret' => '11111111100011']);
    $cfaB = Organisation::factory()->create(['nom' => 'CFA Concurrent', 'siret' => '99999999900099']);

    expect(dansCeCfa($cfaA, fn () => Organisation::courante()->siret))->toBe('11111111100011')
        ->and(dansCeCfa($cfaB, fn () => Organisation::courante()->siret))->toBe('99999999900099');
});

it('n’imprime jamais le SIRET d’un CFA sur le CERFA d’un autre', function () {
    $cfaA = Organisation::factory()->create([
        'nom' => 'CFA V2S',
        'raison_sociale' => 'CFA V2S',
        'siret' => '11111111100011',
        'representant_nom' => 'Durand',
    ]);
    $cfaB = Organisation::factory()->create([
        'nom' => 'CFA Concurrent',
        'raison_sociale' => 'CFA Concurrent',
        'siret' => '99999999900099',
        'representant_nom' => 'Lefevre',
    ]);

    $contratB = dansCeCfa($cfaB, fn () => Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]));

    $champs = dansCeCfa($cfaB, fn () => app(CerfaApprentissage::class)->champs($contratB));

    // Le document du CFA B porte l'identité du CFA B, jamais celle du CFA A.
    // (Le CERFA met le SIRET en forme : « 999 999 999 00099 ».)
    expect($champs['cfa_siret'] ?? null)->toBe('999 999 999 00099')
        ->and($champs['cfa_denomination'] ?? null)->toBe('CFA Concurrent');

    // Et le CFA A garde le sien.
    $contratA = dansCeCfa($cfaA, fn () => Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]));
    $champsA = dansCeCfa($cfaA, fn () => app(CerfaApprentissage::class)->champs($contratA));

    expect($champsA['cfa_siret'] ?? null)->toBe('111 111 111 00011')
        ->and($champsA['cfa_denomination'] ?? null)->toBe('CFA V2S');
});

it('n’a plus qu’un seul « nom du CFA », celui qui figure sur les documents', function () {
    // Il y en avait deux : organisations.nom (sélecteur de tenant) et
    // cfa_profiles.nom (documents). La Fiche du CFA annonçait « Apparaît […] sur
    // vos documents » sous celui qui n'y figurait pas. Renommer son CFA ne
    // changeait rien aux CERFA.
    $cfa = Organisation::factory()->create([
        'nom' => 'CFA Rebaptisé',
        'raison_sociale' => null,
    ]);

    $contrat = dansCeCfa($cfa, fn () => Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]));

    $champs = dansCeCfa($cfa, fn () => app(CerfaApprentissage::class)->champs($contrat));

    expect($champs['cfa_denomination'] ?? null)->toBe('CFA Rebaptisé')
        ->and($cfa->getFilamentName())->toBe('CFA Rebaptisé');
});

it('retombe sur le CFA par défaut hors contexte tenant (jobs, commandes)', function () {
    Organisation::factory()->create(['slug' => 'cfa-autre', 'nom' => 'Autre CFA']);

    // Un job en file, une commande planifiée ou un formulaire public n'ont pas de
    // tenant courant : la génération ne doit ni planter ni inventer une identité
    // vide. Elle retombe sur le CFA par défaut (le plus ancien actif).
    Filament::setTenant(null, isQuiet: true);

    expect(Organisation::courante()->is(Organisation::defaut()))->toBeTrue()
        ->and(Organisation::courante()->exists)->toBeTrue();
});
