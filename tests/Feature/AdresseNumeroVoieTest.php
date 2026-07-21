<?php

use App\Support\AdresseBan;
use App\Support\EntrepriseAnnuaire;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Séparation numéro de voirie / nom de voie
|--------------------------------------------------------------------------
| La BAN fusionne le numéro et la rue dans « name » (« 59 Rue la Fayette ») ;
| les cases « Numéro » des formulaires et du CERFA attendent les deux séparés.
*/

function banFeature(array $properties): array
{
    return ['features' => [[
        'geometry' => ['type' => 'Point', 'coordinates' => [2.3435, 48.8763]],
        'properties' => $properties,
    ]]];
}

it('sépare le numéro de voirie du nom de la voie', function () {
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(banFeature([
        'label' => '59 Rue la Fayette 75009 Paris',
        'name' => '59 Rue la Fayette',
        'housenumber' => '59',
        'street' => 'Rue la Fayette',
        'postcode' => '75009',
        'city' => 'Paris',
    ]))]);

    $data = AdresseBan::decode(array_key_first(
        app(AdresseBan::class)->options('59 rue la fayette paris')
    ));

    expect($data['numero'])->toBe('59')
        ->and($data['voie'])->toBe('Rue la Fayette')
        ->and($data['adresse'])->toBe('59 Rue la Fayette');
});

it('conserve le numéro saisi quand la BAN ne connaît que la rue', function () {
    // La BAN dégrade en résultat « street » et perd le numéro saisi.
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(banFeature([
        'label' => 'Rue Charles de Gaulle 42000 Saint-Étienne',
        'name' => 'Rue Charles de Gaulle',
        'street' => 'Rue Charles de Gaulle',
        'postcode' => '42000',
        'city' => 'Saint-Étienne',
    ]))]);

    $options = app(AdresseBan::class)->options('228 rue charles de gaulle');
    $data = AdresseBan::decode(array_key_first($options));

    expect($data['numero'])->toBe('228')
        ->and($data['voie'])->toBe('Rue Charles de Gaulle')
        ->and($data['adresse'])->toBe('228 Rue Charles de Gaulle')
        // Le libellé affiché montre le numéro qui sera réellement enregistré.
        ->and(reset($options))->toStartWith('228 ');
});

it('ne prend pas un code postal en tête pour un numéro de voirie', function () {
    Http::fake(['api-adresse.data.gouv.fr/*' => Http::response(banFeature([
        'label' => 'Rue Charles de Gaulle 42000 Saint-Étienne',
        'name' => 'Rue Charles de Gaulle',
        'street' => 'Rue Charles de Gaulle',
        'postcode' => '42000',
        'city' => 'Saint-Étienne',
    ]))]);

    $data = AdresseBan::decode(array_key_first(
        app(AdresseBan::class)->options('42000 rue charles de gaulle')
    ));

    expect($data['numero'])->toBeNull()
        ->and($data['voie'])->toBe('Rue Charles de Gaulle');
});

it('sépare le numéro du siège renvoyé par l\'Annuaire des Entreprises', function () {
    Http::fake(['recherche-entreprises.api.gouv.fr/*' => Http::response([
        'results' => [[
            'siren' => '552032534',
            'nom_raison_sociale' => 'DANONE',
            'nature_juridique' => '5599',
            'activite_principale' => '70.10Z',
            'siege' => [
                'siret' => '55203253400703',
                'numero_voie' => '59',
                'type_voie' => 'RUE',
                'libelle_voie' => 'LA FAYETTE',
                'complement_adresse' => 'BAT B',
                'code_postal' => '75009',
                'libelle_commune' => 'PARIS',
            ],
        ]],
    ])]);

    $fiche = EntrepriseAnnuaire::decode(array_key_first(
        app(EntrepriseAnnuaire::class)->options('danone')
    ));

    expect($fiche['numero'])->toBe('59')
        ->and($fiche['voie'])->toBe('RUE LA FAYETTE')
        ->and($fiche['adresse'])->toBe('59 RUE LA FAYETTE')
        ->and($fiche['complement_adresse'])->toBe('BAT B');
});

/*
|--------------------------------------------------------------------------
| Enrichissement entreprise depuis l'Annuaire des Entreprises
|--------------------------------------------------------------------------
*/

function ficheAnnuaire(array $resultat): ?array
{
    Http::fake(['recherche-entreprises.api.gouv.fr/*' => Http::response(['results' => [$resultat]])]);

    return EntrepriseAnnuaire::decode(array_key_first(
        app(EntrepriseAnnuaire::class)->options('test')
    ));
}

it('libelle la forme juridique et l\'activité depuis les nomenclatures officielles', function () {
    $fiche = ficheAnnuaire([
        'siren' => '552032534',
        'nom_raison_sociale' => 'DANONE',
        'nature_juridique' => '5599',
        'activite_principale' => '70.10Z',
        'siege' => ['siret' => '55203253400703', 'libelle_commune' => 'PARIS'],
    ]);

    expect($fiche['forme_juridique'])->toBe("SA à conseil d'administration (s.a.i.)")
        ->and($fiche['activite_libelle'])->toBe('Activités des sièges sociaux');
});

it('retient le dirigeant qui engage la société et expose sa qualité', function () {
    $fiche = ficheAnnuaire([
        'nom_raison_sociale' => 'ACME',
        'siege' => ['siret' => '81234567800012'],
        'dirigeants' => [
            ['nom' => 'DUPONT', 'prenoms' => 'PAUL', 'qualite' => 'Administrateur', 'type_dirigeant' => 'personne physique'],
            ['denomination' => 'CABINET X', 'qualite' => 'Commissaire aux comptes titulaire', 'type_dirigeant' => 'personne morale'],
            ['nom' => 'MARTIN', 'prenoms' => 'SOPHIE', 'qualite' => 'Président', 'type_dirigeant' => 'personne physique'],
        ],
    ]);

    // La présidente passe devant l'administrateur ; la personne morale est écartée.
    expect($fiche['dirigeants'])->toHaveCount(2)
        ->and($fiche['dirigeant_nom'])->toBe('MARTIN')
        ->and($fiche['dirigeant_prenom'])->toBe('SOPHIE')
        ->and($fiche['dirigeant_qualite'])->toBe('Président');
});

it('récupère le nom commercial et l\'IDCC en ignorant les codes techniques', function () {
    $fiche = ficheAnnuaire([
        'nom_raison_sociale' => 'ACME',
        'siege' => [
            'siret' => '81234567800012',
            'liste_enseignes' => ['CHEZ ACME'],
            'liste_idcc' => ['9999', '1486'],
        ],
    ]);

    expect($fiche['nom_commercial'])->toBe('CHEZ ACME')
        ->and($fiche['code_idcc'])->toBe('1486');
});

it('n\'invente pas d\'IDCC quand seuls des codes techniques sont déclarés', function () {
    $fiche = ficheAnnuaire([
        'nom_raison_sociale' => 'ACME',
        'siege' => ['siret' => '81234567800012', 'liste_idcc' => ['9998']],
    ]);

    expect($fiche['code_idcc'])->toBeNull();
});
