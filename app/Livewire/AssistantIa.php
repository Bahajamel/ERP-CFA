<?php

namespace App\Livewire;

use App\Models\FaqBot;
use App\Models\FaqEntry;
use App\Support\Assistant\AssistantContexte;
use App\Support\Assistant\RechercheFaq;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Assistant FAQ de la partie du logiciel où se trouve l'utilisateur.
 *
 * Il n'y a pas UN chatbot global mais plusieurs assistants spécialisés
 * (Commercial, Contrats & OPCO, Finance, Scolarité, Pilotage) : celui qui
 * s'affiche est déterminé par la page consultée ({@see AssistantContexte}), et
 * ses réponses sont bornées à son propre périmètre.
 *
 * Aucune IA, aucun appel externe : les réponses sont des entrées de FAQ rédigées
 * par le CFA et modifiables depuis l'administration.
 */
class AssistantIa extends Component
{
    /** Assistant affiché (null = aucun assistant installé → rien ne s'affiche). */
    public ?int $botId = null;

    /** Historique de la conversation (messages utilisateur + assistant). */
    public array $messages = [];

    /** Saisie courante. */
    public string $question = '';

    /**
     * @param  ?string  $module  force un assistant précis ; par défaut, il est
     *                           déduit de la page consultée.
     */
    public function mount(?string $module = null): void
    {
        $bot = $module !== null
            ? AssistantContexte::botPourModule($module)
            : AssistantContexte::botCourant();

        if ($bot === null) {
            return;
        }

        $this->botId = $bot->id;

        $this->messages[] = [
            'role' => 'bot',
            // « accueil » : ses suggestions s'affichent sous « Questions rapides »
            // (les suivantes, elles, sous « Questions liées »).
            'type' => 'accueil',
            'texte' => $bot->welcome_message,
            'liens' => [],
            'suggestions' => RechercheFaq::suggestions($bot),
        ];
    }

    /** L'assistant courant, rechargé à chaque requête Livewire. */
    public function getBotProperty(): ?FaqBot
    {
        return $this->botId !== null ? FaqBot::find($this->botId) : null;
    }

    /** Envoie la question saisie. */
    public function envoyer(): void
    {
        $texte = trim($this->question);

        if ($texte === '' || $this->bot === null) {
            return;
        }

        $this->messages[] = ['role' => 'user', 'type' => 'question', 'texte' => $texte, 'liens' => [], 'suggestions' => []];
        $this->messages[] = $this->repondre($texte);
        $this->question = '';

        $this->dispatch('assistant-defiler');
    }

    /** Rejoue une question suggérée en un clic. */
    public function demander(string $suggestion): void
    {
        $this->question = $suggestion;
        $this->envoyer();
    }

    /**
     * Clic sur une question proposée. On transmet l'IDENTIFIANT et non le texte :
     * une apostrophe ou une barre oblique dans la question suffirait à casser
     * l'appel côté navigateur.
     *
     * L'entrée est cherchée DANS l'assistant courant : impossible d'obtenir la
     * réponse d'une autre section en forgeant un identifiant.
     */
    public function poser(int $entreeId): void
    {
        $bot = $this->bot;

        if ($bot === null) {
            return;
        }

        $entree = $bot->entrees()->actif()->whereKey($entreeId)->first();

        if ($entree === null) {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'type' => 'question',
            'texte' => $entree->question,
            'liens' => [],
            'suggestions' => [],
        ];

        $this->messages[] = $this->reponseDepuis($entree, $this->autresQue($bot, $entree));
        $this->question = '';

        $this->dispatch('assistant-defiler');
    }

    /**
     * Quelques autres questions du même assistant, pour la rubrique
     * « Questions liées ».
     *
     * @return list<array{id: int, question: string}>
     */
    private function autresQue(FaqBot $bot, FaqEntry $entree): array
    {
        return $bot->entrees()->actif()
            ->whereKeyNot($entree->id)
            ->limit(3)
            ->get()
            ->map(fn (FaqEntry $e): array => ['id' => $e->id, 'question' => $e->question])
            ->all();
    }

    /**
     * Construit la réponse : la meilleure entrée de CET assistant, plus les
     * suivantes proposées en « Voir aussi ».
     */
    private function repondre(string $message): array
    {
        $bot = $this->bot;
        $resultats = RechercheFaq::rechercher($bot, $message);

        if ($resultats->isEmpty()) {
            return [
                'role' => 'bot',
                // Aucune réponse : on repropose les questions fréquentes.
                'type' => 'vide',
                'texte' => "Je n'ai pas trouvé de réponse dans cette FAQ. Essayez de reformuler, ou contactez un administrateur. Voici les sujets que je couvre :",
                'liens' => [],
                'suggestions' => RechercheFaq::suggestions($bot),
            ];
        }

        /** @var FaqEntry $principal */
        $principal = $resultats->shift();

        // Les autres résultats deviennent des questions liées, cliquables.
        return $this->reponseDepuis($principal, $resultats
            ->map(fn (FaqEntry $e): array => ['id' => $e->id, 'question' => $e->question])
            ->all());
    }

    /**
     * Message de réponse à partir d'une entrée de FAQ.
     *
     * @param  list<array{id: int, question: string}>  $liees
     */
    private function reponseDepuis(FaqEntry $entree, array $liees): array
    {
        return [
            'role' => 'bot',
            // « reponse » : affichée sous le libellé « Réponse », suivie des
            // questions liées.
            'type' => 'reponse',
            'texte' => $entree->answer,
            'liens' => array_values(array_filter([$this->resoudreLien($entree)])),
            'suggestions' => $liees,
        ];
    }

    /**
     * Lien vers la page concernée. Masqué si l'utilisateur n'a pas la permission
     * (la réponse texte, elle, reste affichée), ou si la route n'existe pas.
     */
    private function resoudreLien(FaqEntry $entree): ?array
    {
        $lien = $entree->lien();

        if ($lien === null || ! app('router')->has($lien['route'])) {
            return null;
        }

        return ['url' => route($lien['route']), 'label' => $lien['label']];
    }

    public function render(): View
    {
        return view('livewire.assistant-ia', ['bot' => $this->bot]);
    }
}
