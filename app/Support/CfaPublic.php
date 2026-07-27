<?php

namespace App\Support;

use App\Models\Organisation;
use Illuminate\Http\Request;

/**
 * Résolution du CFA destinataire d'un formulaire PUBLIC (candidature, entreprise
 * partenaire).
 *
 * Ces pages sont ouvertes à des visiteurs non connectés : Filament n'a pas de
 * tenant, et rien dans la session ne dit à quel CFA le visiteur s'adresse. Le
 * CFA vient donc de l'URL — /candidature/{cfa} — puis voyage jusqu'à l'envoi
 * dans un champ caché du formulaire.
 *
 * Pourquoi un champ caché plutôt que la session : deux onglets ouverts sur deux
 * CFA différents partagent la même session, et le dernier écraserait le premier.
 * Le champ caché rattache chaque envoi au CFA de la page réellement remplie.
 *
 * Sans segment de CFA, on retombe sur le CFA par défaut : les anciens liens
 * /candidature et /entreprise continuent de fonctionner à l'identique.
 */
class CfaPublic
{
    /** Nom du champ caché qui transporte le CFA jusqu'à l'envoi du formulaire. */
    public const CHAMP = 'cfa';

    /**
     * CFA désigné par un slug d'URL. Absent → CFA par défaut (lien historique).
     * Slug inconnu ou CFA désactivé → 404 : mieux vaut une erreur franche qu'un
     * dossier silencieusement rattaché au mauvais CFA.
     */
    public static function resoudre(?string $slug): ?Organisation
    {
        if (blank($slug)) {
            return Organisation::defaut();
        }

        $organisation = Organisation::query()
            ->where('slug', $slug)
            ->where('actif', true)
            ->first();

        abort_if($organisation === null, 404, 'Ce lien ne correspond à aucun CFA actif.');

        return $organisation;
    }

    /** CFA porté par le formulaire envoyé (champ caché), sinon CFA par défaut. */
    public static function depuisRequete(Request $request): ?Organisation
    {
        $slug = $request->input(self::CHAMP);

        return self::resoudre(is_string($slug) ? $slug : null);
    }

    /**
     * Lien public à distribuer pour un CFA donné (bouton « Lien candidature » /
     * « Lien entreprise » de l'ERP).
     */
    public static function lien(string $route, mixed $organisation): string
    {
        // Accepte le tenant Filament tel quel (Model|null) : hors contexte CFA on
        // retombe sur le lien sans slug, qui vise le CFA par défaut.
        $slug = $organisation instanceof Organisation ? $organisation->slug : null;

        return route($route, $slug !== null ? ['cfa' => $slug] : []);
    }
}
