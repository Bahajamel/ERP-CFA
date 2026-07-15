<?php

namespace App\Livewire;

use App\Filament\Resources\Needs\NeedResource;
use App\Matching\PropositionService;
use App\Models\Need;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Formulaire « Proposer des candidats » (modale) : sélection multiple de
 * candidats compatibles avec un besoin, préparation de la proposition (canal,
 * relance, responsable, message) puis création des matchings + tâches de relance.
 */
class ProposerCandidatsModal extends Component
{
    public int $needId;

    /** @var array<int, int> ids des candidats sélectionnés */
    public array $selection = [];

    public string $recherche = '';

    public ?string $filtreCv = null;         // 'oui' | 'non'

    public ?string $filtreDisponibilite = null;

    public string $canal = 'email';

    public ?string $dateRelance = null;

    public ?int $responsableId = null;

    public string $commentaire = '';

    public string $message = '';

    public int $limite = 6;

    public function mount(int $needId): void
    {
        $this->needId = $needId;
        $this->responsableId = auth()->id();
        $this->dateRelance = now()->addDays(5)->format('Y-m-d');
        $this->message = $this->messageParDefaut();
    }

    // ─── Données ─────────────────────────────────────────────────────────

    #[Computed]
    public function need(): ?Need
    {
        return Need::with(['company', 'formation', 'contact'])->find($this->needId);
    }

    /** Lignes candidats compatibles, filtrées et prêtes pour l'affichage. */
    #[Computed]
    public function candidats(): Collection
    {
        $need = $this->need;

        if ($need === null) {
            return collect();
        }

        return $need->candidatsCompatibles(50)
            ->map(function (array $row): array {
                $c = $row['candidate'];
                $cvDispo = $c->getFirstMedia('cv') !== null;

                return [
                    'id' => $c->id,
                    'nom' => $c->nom_complet,
                    'initiales' => $c->initiales,
                    'formation' => $c->formationVisee?->libelle ?? '—',
                    'statut' => $c->statut?->getLabel() ?? '—',
                    'mobilite' => $c->mobilite ?: '—',
                    'disponibilite' => $c->disponibilite ?: '—',
                    'cvDispo' => $cvDispo,
                    'score' => (int) $row['score'],
                    'pointFort' => $c->niveau_actuel ?: $row['explication'],
                ];
            })
            ->when($this->recherche !== '', fn (Collection $rows) => $rows->filter(
                fn (array $r): bool => str_contains(mb_strtolower($r['nom']), mb_strtolower($this->recherche))
            ))
            ->when($this->filtreCv !== null, fn (Collection $rows) => $rows->filter(
                fn (array $r): bool => $this->filtreCv === 'oui' ? $r['cvDispo'] : ! $r['cvDispo']
            ))
            ->when($this->filtreDisponibilite !== null && $this->filtreDisponibilite !== '', fn (Collection $rows) => $rows->filter(
                fn (array $r): bool => $r['disponibilite'] === $this->filtreDisponibilite
            ))
            ->take($this->limite)
            ->values();
    }

    /** Options de disponibilité présentes chez les candidats compatibles. */
    #[Computed]
    public function disponibilites(): array
    {
        return $this->need?->candidatsCompatibles(50)
            ->pluck('candidate.disponibilite')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];
    }

    #[Computed]
    public function responsables(): array
    {
        return User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    // ─── Interactions ────────────────────────────────────────────────────

    public function toggle(int $id): void
    {
        if (in_array($id, $this->selection, true)) {
            $this->selection = array_values(array_diff($this->selection, [$id]));
        } else {
            $this->selection[] = $id;
        }
    }

    public function chargerPlus(): void
    {
        $this->limite += 6;
    }

    public function valider(): mixed
    {
        return $this->enregistrer(envoi: true);
    }

    public function brouillon(): mixed
    {
        return $this->enregistrer(envoi: false);
    }

    private function enregistrer(bool $envoi): mixed
    {
        $need = $this->need;

        if ($need === null) {
            return null;
        }

        if ($this->selection === []) {
            $this->erreur('Aucun candidat sélectionné', 'Cochez au moins un candidat à proposer.');

            return null;
        }

        if ($envoi && blank($this->dateRelance)) {
            $this->erreur('Date de relance obligatoire', 'Renseignez une date de relance avant de valider.');

            return null;
        }

        if ($envoi && blank($this->responsableId)) {
            $this->erreur('Responsable obligatoire', 'Choisissez un responsable du suivi.');

            return null;
        }

        if ($need->estCloture()) {
            $this->erreur('Ce besoin est déjà pourvu', 'Impossible de proposer des candidats sur un besoin clôturé.');

            return null;
        }

        $resultat = app(PropositionService::class)->proposer(
            $need,
            $this->selection,
            [
                'canal' => $this->canal,
                'responsableId' => $this->responsableId,
                'dateRelance' => $this->dateRelance,
                'commentaire' => $this->commentaire ?: null,
                'message' => $this->message ?: null,
            ],
            envoi: $envoi,
        );

        $count = $resultat['count'];

        if (! $envoi) {
            Notification::make()->success()
                ->title($count.' candidat(s) enregistré(s) en brouillon')
                ->send();

            return $this->redirect(NeedResource::getUrl('index'));
        }

        // Proposition envoyée : email au contact entreprise (si renseigné) + relances.
        $destinataire = $resultat['destinataire'];

        Notification::make()
            ->success()
            ->title($count.' candidat(s) proposé(s) à '.($need->company?->raison_sociale ?? 'l\'entreprise'))
            ->body($destinataire !== null
                ? 'Proposition envoyée à '.$destinataire.'. Tâches de relance créées.'
                : 'Aucun email de contact pour cette entreprise — proposition enregistrée, à transmettre manuellement.')
            ->send();

        return $this->redirect(NeedResource::getUrl('index'));
    }

    private function erreur(string $titre, string $corps): void
    {
        Notification::make()->danger()->title($titre)->body($corps)->send();
    }

    private function messageParDefaut(): string
    {
        $need = $this->need;
        $contact = $need?->contact?->nom_complet ?? 'Madame, Monsieur';
        $poste = $need?->intitule_poste ?? 'le poste';

        return "Bonjour {$contact},\n\n"
            ."Veuillez trouver ci-joints les profils de candidats sélectionnés pour le poste de « {$poste} » "
            ."au sein de votre établissement.\n\n"
            ."N'hésitez pas à me contacter pour tout complément d'information.\n\n"
            .'Cordialement,'."\n".(auth()->user()?->name ?? '');
    }

    public function render(): View
    {
        return view('livewire.proposer-candidats-modal');
    }
}
