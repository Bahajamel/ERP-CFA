<?php

namespace App\Scolarite;

use App\Models\Candidate;
use App\Models\CandidatePromotion;
use App\Models\Promotion;
use Illuminate\Support\Str;

/**
 * Inscription d'un apprenant en scolarité après validation de son admission :
 * rattachement à une cohorte, invitation tokenisée à choisir ses matières, puis
 * enregistrement de sa réponse. Logique métier centralisée (réutilisée par
 * l'action d'admission et le formulaire public), sans dépendance à l'UI.
 */
class InscriptionService
{
    /** Durée de validité d'une invitation. */
    public const EXPIRATION_JOURS = 30;

    /**
     * Rattache l'apprenant à la cohorte et (ré)émet une invitation à choisir ses
     * matières. Idempotent : renvoyer une invitation régénère le jeton sans
     * perdre l'appartenance à la classe.
     */
    public function inviter(Candidate $candidate, Promotion $promotion): CandidatePromotion
    {
        $candidate->promotions()->syncWithoutDetaching([
            $promotion->id => [
                'invitation_token' => $this->jetonUnique(),
                'invited_at' => now(),
            ],
        ]);

        return $this->pivot($candidate->id, $promotion->id);
    }

    /** Retrouve l'inscription (pivot) par son jeton d'invitation, ou null. */
    public function parToken(string $token): ?CandidatePromotion
    {
        return CandidatePromotion::query()
            ->where('invitation_token', $token)
            ->first();
    }

    /** L'invitation est-elle encore valide (émise et non expirée) ? */
    public function invitationValide(CandidatePromotion $pivot): bool
    {
        return $pivot->invited_at !== null
            && $pivot->invited_at->copy()->addDays(self::EXPIRATION_JOURS)->isFuture();
    }

    /**
     * Enregistre les matières choisies par l'apprenant (bornées au programme de
     * sa formation) et marque la réponse — il est dès lors inscrit en scolarité.
     *
     * @param  array<int, string>  $matieres
     */
    public function enregistrerChoix(CandidatePromotion $pivot, array $matieres): CandidatePromotion
    {
        $programme = $pivot->promotion?->formation?->programme() ?? [];
        $retenues = array_values(array_intersect($programme, array_map('trim', $matieres)));

        $pivot->forceFill([
            'matieres' => $retenues,
            'responded_at' => now(),
        ])->save();

        return $pivot->refresh();
    }

    private function pivot(int $candidateId, int $promotionId): CandidatePromotion
    {
        return CandidatePromotion::query()
            ->where('candidate_id', $candidateId)
            ->where('promotion_id', $promotionId)
            ->firstOrFail();
    }

    private function jetonUnique(): string
    {
        do {
            $token = Str::random(48);
        } while (CandidatePromotion::query()->where('invitation_token', $token)->exists());

        return $token;
    }
}
