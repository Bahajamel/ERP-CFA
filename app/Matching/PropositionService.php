<?php

namespace App\Matching;

use App\Enums\MatchingStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Models\Task;

/**
 * Concrétise une proposition de candidats sur un besoin entreprise :
 * crée un matching par candidat, et — pour une proposition réellement envoyée —
 * une tâche de relance assignée au responsable du suivi. L'historique est tracé
 * automatiquement par l'activity log du matching.
 */
class PropositionService
{
    /**
     * @param  array<int, int>  $candidateIds
     * @param  array{canal?: string, responsableId?: int|null, dateRelance?: string|null, commentaire?: string|null, message?: string|null}  $data
     * @param  bool  $envoi  true = proposition envoyée (statut + relance) ; false = brouillon (En recherche)
     * @return int Nombre de candidats réellement proposés (doublons ignorés).
     */
    public function proposer(Need $need, array $candidateIds, array $data, bool $envoi = true): int
    {
        $statut = $envoi ? MatchingStatut::PropositionEnvoyee : MatchingStatut::EnRecherche;
        $responsableId = $data['responsableId'] ?? auth()->id();
        $proposes = 0;

        foreach (array_unique($candidateIds) as $candidateId) {
            // Doublon candidat × besoin : on n'en recrée pas.
            if ($need->matchings()->where('candidate_id', $candidateId)->exists()) {
                continue;
            }

            $candidate = Candidate::find($candidateId);

            if ($candidate === null) {
                continue;
            }

            $matching = $need->matchings()->create([
                'candidate_id' => $candidate->id,
                'statut' => $statut->value,
                'cv_envoye' => $envoi && $candidate->getFirstMedia('cv') !== null,
                'assigned_by' => auth()->id(),
                'responsable_suivi_id' => $responsableId,
                'canal' => $envoi ? ($data['canal'] ?? null) : null,
                'next_action_at' => $envoi ? ($data['dateRelance'] ?? null) : null,
                'date_proposition' => $envoi ? now() : null,
                'message_presentation' => $envoi ? ($data['message'] ?? null) : null,
                'commentaire_interne' => $data['commentaire'] ?? null,
            ]);

            if ($envoi) {
                $this->creerTacheRelance($need, $candidate, $matching, $responsableId, $data['dateRelance'] ?? null);
            }

            $proposes++;
        }

        return $proposes;
    }

    /** Tâche de relance de la proposition, assignée au responsable du suivi. */
    private function creerTacheRelance(Need $need, Candidate $candidate, Matching $matching, ?int $responsableId, ?string $dateRelance): void
    {
        Task::create([
            'titre' => 'Relancer '.($need->company?->raison_sociale ?? 'l\'entreprise').' — '.$candidate->nom_complet,
            'description' => 'Relance de la proposition de '.$candidate->nom_complet.' pour le poste « '.$need->intitule_poste.' ».',
            'taskable_type' => $matching->getMorphClass(),
            'taskable_id' => $matching->id,
            'assignee_id' => $responsableId,
            'created_by' => auth()->id(),
            'due_date' => $dateRelance,
            'priorite' => TaskPriorite::Normale->value,
            'statut' => TaskStatut::AFaire->value,
            'source' => 'manuel',
        ]);
    }
}
