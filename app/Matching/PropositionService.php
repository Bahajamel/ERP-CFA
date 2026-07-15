<?php

namespace App\Matching;

use App\Enums\MatchingStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Mail\PropositionCandidats;
use App\Models\Candidate;
use App\Models\CompanyContact;
use App\Models\Matching;
use App\Models\Need;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

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
     * @param  bool  $envoi  true = proposition envoyée (statut + relance + email) ; false = brouillon (En recherche)
     * @return array{count: int, destinataire: ?string} Nombre proposé (doublons ignorés) et email du contact averti.
     */
    public function proposer(Need $need, array $candidateIds, array $data, bool $envoi = true): array
    {
        $statut = $envoi ? MatchingStatut::PropositionEnvoyee : MatchingStatut::EnRecherche;
        $responsableId = $data['responsableId'] ?? auth()->id();
        $proposes = collect();

        foreach (array_unique($candidateIds) as $candidateId) {
            // Doublon candidat × besoin : on n'en recrée pas.
            if ($need->matchings()->where('candidate_id', $candidateId)->exists()) {
                continue;
            }

            $candidate = Candidate::find($candidateId);

            // Sans consentement RGPD, un candidat n'est jamais proposé (garde-fou serveur).
            if ($candidate === null || ! $candidate->cv_consentement) {
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

            $proposes->push($candidate);
        }

        // Envoi réel de la proposition au contact (RH / responsable) de l'entreprise.
        $destinataire = null;

        if ($envoi && $proposes->isNotEmpty()) {
            $destinataire = $this->envoyerAuContact($need, $proposes, $data);
        }

        return ['count' => $proposes->count(), 'destinataire' => $destinataire];
    }

    /**
     * Envoie la proposition par email au contact de l'entreprise partenaire.
     * Retourne l'adresse notifiée, ou null si aucun contact n'a d'email.
     *
     * @param  Collection<int, Candidate>  $candidats
     */
    private function envoyerAuContact(Need $need, Collection $candidats, array $data): ?string
    {
        $contact = $this->contactEntreprise($need);

        if ($contact === null || blank($contact->email)) {
            return null;
        }

        $responsable = optional(\App\Models\User::find($data['responsableId'] ?? null))->name;

        Mail::to($contact->email)->send(new PropositionCandidats(
            $need,
            $candidats,
            $data['message'] ?? '',
            $responsable,
        ));

        return $contact->email;
    }

    /**
     * Contact destinataire côté entreprise (CRM partenaires) : le contact du
     * besoin en priorité, sinon le contact principal, sinon le premier contact
     * disposant d'un email.
     */
    private function contactEntreprise(Need $need): ?CompanyContact
    {
        $need->loadMissing('contact', 'company.contacts');

        return collect([$need->contact])
            ->merge($need->company?->contacts->sortByDesc('is_principal') ?? collect())
            ->filter(fn (?CompanyContact $c): bool => $c !== null && filled($c->email))
            ->first();
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
