<?php

namespace App\Provisioning;

use App\Models\Organisation;
use App\Models\User;
use App\Qualiopi\ReferentielQualiopi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ouvre un CFA en essai gratuit : crée l'organisation (tenant), son compte
 * administrateur et fixe l'échéance de l'essai.
 *
 * Les référentiels (indicateurs Qualiopi, missions L6231-2, OPCO, NPEC, rôles
 * et permissions) sont GLOBAUX, partagés par tous les CFA : il n'y a rien à
 * seeder par CFA. Le nouveau centre arrive donc sur un espace fonctionnel où il
 * saisit ses propres données (candidats, entreprises, contrats…) — celles-ci se
 * rattachent automatiquement à son organisation via BelongsToOrganisation.
 *
 * Le blocage d'accès à l'échéance repose sur la colonne `actif` déjà en place
 * (cf. User::canAccessTenant) : la commande essai:suspendre-expires bascule
 * `actif` à false quand la date de fin est dépassée.
 */
class ProvisionnerCfaEssai
{
    /** Durée d'essai par défaut, en jours. */
    public const JOURS_ESSAI_DEFAUT = 30;

    /**
     * @return array{
     *     organisation: Organisation,
     *     admin: User,
     *     mot_de_passe: ?string,
     *     compte_existant: bool
     * } Le mot de passe n'est renvoyé QUE pour un compte nouvellement créé, afin
     *   d'être transmis une seule fois au CFA. Pour un e-mail déjà connu, on
     *   rattache le compte existant sans toucher à son mot de passe.
     */
    public function creer(string $nom, string $emailAdmin, ?string $nomAdmin = null, int $joursEssai = self::JOURS_ESSAI_DEFAUT): array
    {
        $emailAdmin = mb_strtolower(trim($emailAdmin));

        return DB::transaction(function () use ($nom, $emailAdmin, $nomAdmin, $joursEssai): array {
            $organisation = Organisation::create([
                'nom' => $nom,
                'slug' => $this->slugUnique($nom),
                'actif' => true,
                'date_fin_essai' => now()->addDays($joursEssai),
            ]);

            // Référentiel Qualiopi propre au CFA (32 indicateurs, état vierge) :
            // sans lui, le module Qualité du nouvel espace serait vide.
            ReferentielQualiopi::provisionner($organisation->id);

            [$admin, $motDePasse, $compteExistant] = $this->administrateur($emailAdmin, $nomAdmin);

            // Rôle CFA le plus large (jamais access_editeur — cf. RolePermissionSeeder)
            // et rattachement au nouveau CFA sans détacher ses éventuels autres CFA.
            $admin->syncRoles(['Administrateur']);
            $organisation->users()->syncWithoutDetaching($admin);

            return [
                'organisation' => $organisation,
                'admin' => $admin,
                'mot_de_passe' => $motDePasse,
                'compte_existant' => $compteExistant,
            ];
        });
    }

    /**
     * Compte administrateur du CFA : réutilise un compte existant (même e-mail)
     * sans réinitialiser son mot de passe, sinon en crée un avec un mot de passe
     * temporaire lisible, à transmettre une seule fois.
     *
     * @return array{0: User, 1: ?string, 2: bool}
     */
    private function administrateur(string $email, ?string $nomAdmin): array
    {
        $existant = User::query()->where('email', $email)->first();

        if ($existant !== null) {
            // Le compte pourrait avoir été désactivé : on le réactive puisqu'on
            // lui ouvre un accès. On ne touche NI à son mot de passe, NI à son nom.
            $existant->forceFill(['is_active' => true])->save();

            return [$existant, null, true];
        }

        // Mot de passe temporaire sans symboles : plus facile à transmettre.
        $motDePasse = Str::password(12, symbols: false);

        $admin = User::create([
            'name' => $nomAdmin !== null && trim($nomAdmin) !== '' ? trim($nomAdmin) : $email,
            'email' => $email,
            'password' => Hash::make($motDePasse),
            'is_active' => true,
        ]);

        return [$admin, $motDePasse, false];
    }

    /**
     * Slug dérivé du nom, garanti unique (suffixe numérique si déjà pris).
     * La colonne slug porte une contrainte d'unicité : on l'anticipe ici pour
     * un message clair plutôt qu'une erreur SQL.
     */
    private function slugUnique(string $nom): string
    {
        $base = Str::slug($nom) ?: 'cfa';
        $slug = $base;
        $n = 1;

        while (Organisation::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
