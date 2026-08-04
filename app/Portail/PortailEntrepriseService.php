<?php

namespace App\Portail;

use App\Models\Company;
use Illuminate\Support\Str;

/**
 * Accès de l'entreprise à son espace personnel (portail sans mot de passe),
 * pendant employeur de [[PortailApprenantService]]. Le jeton vit sur l'entreprise :
 * émis à la demande, réutilisé tant qu'il existe, révocable en le régénérant.
 * Logique centralisée, sans dépendance à l'UI.
 */
class PortailEntrepriseService
{
    /** Lien personnel de l'entreprise vers son espace (émet le jeton au besoin). */
    public function lienPour(Company $company): string
    {
        return route('portail.entreprise', ['token' => $this->jeton($company)]);
    }

    /** Jeton de l'entreprise, généré et persisté à la première demande. */
    public function jeton(Company $company): string
    {
        if (blank($company->portail_token)) {
            $company->forceFill([
                'portail_token' => $this->jetonUnique(),
                'portail_token_created_at' => now(),
            ])->saveQuietly();
        }

        return $company->portail_token;
    }

    /** Révoque l'accès courant et en émet un nouveau (l'ancien lien devient caduc). */
    public function regenerer(Company $company): string
    {
        $company->forceFill([
            'portail_token' => $this->jetonUnique(),
            'portail_token_created_at' => now(),
        ])->saveQuietly();

        return $company->portail_token;
    }

    /**
     * Retrouve l'entreprise par son jeton, tous CFA confondus : la route est
     * publique (aucun tenant courant), on lève donc explicitement le cloisonnement.
     */
    public function parToken(string $token): ?Company
    {
        if (blank($token)) {
            return null;
        }

        return Company::query()
            ->tousLesCfa()
            ->where('portail_token', $token)
            ->first();
    }

    /**
     * E-mail où adresser le lien : le contact principal en priorité, sinon le
     * premier contact de l'entreprise disposant d'une adresse. Null si aucun.
     */
    public function emailDestinataire(Company $company): ?string
    {
        return $company->contactPrincipal()->whereNotNull('email')->value('email')
            ?? $company->contacts()->whereNotNull('email')->value('email');
    }

    private function jetonUnique(): string
    {
        do {
            $token = Str::random(48);
        } while (Company::query()->tousLesCfa()->where('portail_token', $token)->exists());

        return $token;
    }
}
