<?php

namespace App\Scolarite;

use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Presence;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\ServiceFait;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Valide (fige) le service fait d'une promotion pour un mois donné (P1-15-1).
 * Garde : au moins une séance et toutes les séances du mois validées.
 */
class ServiceFaitValidator
{
    /**
     * @throws RuntimeException si le mois n'est pas validable ou déjà validé
     */
    public function valider(Promotion $promotion, int $annee, int $mois, ?int $userId = null): ServiceFait
    {
        if (ServiceFait::where('promotion_id', $promotion->id)->where('annee', $annee)->where('mois', $mois)->exists()) {
            throw new RuntimeException('Ce mois est déjà validé (service fait figé).');
        }

        $seances = $this->seancesDuMois($promotion, $annee, $mois);

        if ($seances->isEmpty()) {
            throw new RuntimeException('Aucune séance sur ce mois pour cette classe.');
        }

        if ($seances->contains(fn (Seance $s): bool => $s->statut !== SeanceStatut::Validee)) {
            throw new RuntimeException('Toutes les séances du mois doivent être validées avant le service fait.');
        }

        return ServiceFait::create([
            'promotion_id' => $promotion->id,
            'annee' => $annee,
            'mois' => $mois,
            'nb_seances' => $seances->count(),
            'nb_heures' => $this->totalHeures($seances),
            'taux_presence' => $this->tauxPresence($seances->pluck('id')->all()),
            'validated_by' => $userId,
            'validated_at' => now(),
        ]);
    }

    /** Séances d'une promotion sur un mois donné. */
    public function seancesDuMois(Promotion $promotion, int $annee, int $mois)
    {
        return Seance::query()
            ->where('promotion_id', $promotion->id)
            ->whereYear('date', $annee)
            ->whereMonth('date', $mois)
            ->get();
    }

    /** Total d'heures réalisées (somme des durées des séances). */
    private function totalHeures($seances): float
    {
        return round($seances->sum(function (Seance $s): float {
            if (blank($s->heure_debut) || blank($s->heure_fin)) {
                return 0.0;
            }

            return Carbon::parse($s->heure_debut)->floatDiffInHours(Carbon::parse($s->heure_fin));
        }), 1);
    }

    /** Taux de présence global du mois (présents / renseignés), en %. */
    private function tauxPresence(array $seanceIds): ?int
    {
        $base = fn () => Presence::whereIn('seance_id', $seanceIds);

        $renseignees = $base()->where('statut', '!=', PresenceStatut::NonRenseigne->value)->count();

        if ($renseignees === 0) {
            return null;
        }

        $presents = $base()->whereIn('statut', array_map(
            fn (PresenceStatut $p) => $p->value,
            PresenceStatut::presents(),
        ))->count();

        return (int) round($presents / $renseignees * 100);
    }
}
