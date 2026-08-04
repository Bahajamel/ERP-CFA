<?php

namespace App\Portail;

use App\Models\Candidate;
use Illuminate\Support\Str;

/**
 * Accès de l'apprenant à son espace personnel (portail sans mot de passe), dans
 * la lignée des liens tokenisés existants (inscription, émargement). Le jeton vit
 * sur le candidat : émis à la demande, réutilisé tant qu'il existe, révocable en
 * le régénérant (l'ancien lien cesse alors de fonctionner). Logique centralisée,
 * sans dépendance à l'UI.
 */
class PortailApprenantService
{
    /** Lien personnel de l'apprenant vers son espace (émet le jeton au besoin). */
    public function lienPour(Candidate $candidate): string
    {
        return route('portail.apprenant', ['token' => $this->jeton($candidate)]);
    }

    /** Jeton de l'apprenant, généré et persisté à la première demande. */
    public function jeton(Candidate $candidate): string
    {
        if (blank($candidate->portail_token)) {
            $candidate->forceFill([
                'portail_token' => $this->jetonUnique(),
                'portail_token_created_at' => now(),
            ])->saveQuietly();
        }

        return $candidate->portail_token;
    }

    /** Révoque l'accès courant et en émet un nouveau (l'ancien lien devient caduc). */
    public function regenerer(Candidate $candidate): string
    {
        $candidate->forceFill([
            'portail_token' => $this->jetonUnique(),
            'portail_token_created_at' => now(),
        ])->saveQuietly();

        return $candidate->portail_token;
    }

    /**
     * Retrouve l'apprenant par son jeton, tous CFA confondus : la route est
     * publique (aucun tenant courant), on lève donc explicitement le cloisonnement.
     */
    public function parToken(string $token): ?Candidate
    {
        if (blank($token)) {
            return null;
        }

        return Candidate::query()
            ->tousLesCfa()
            ->where('portail_token', $token)
            ->first();
    }

    private function jetonUnique(): string
    {
        do {
            $token = Str::random(48);
        } while (Candidate::query()->tousLesCfa()->where('portail_token', $token)->exists());

        return $token;
    }
}
