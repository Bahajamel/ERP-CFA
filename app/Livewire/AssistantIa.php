<?php

namespace App\Livewire;

use App\Support\Assistant\BaseFaq;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Assistant d'aide « Demander à l'IA » : un chatbot local qui répond aux
 * questions d'usage du logiciel à partir d'une base de connaissance interne
 * ([BaseFaq]) et renvoie vers la bonne page de l'ERP. Aucune donnée ne sort,
 * aucune API externe : les réponses sont curées et validées.
 */
class AssistantIa extends Component
{
    /** Historique de la conversation (messages utilisateur + assistant). */
    public array $messages = [];

    /** Saisie courante. */
    public string $question = '';

    public function mount(): void
    {
        $this->messages[] = [
            'role' => 'bot',
            'texte' => "Bonjour 👋 Je suis l'assistant du CFA. Posez-moi une question sur l'utilisation du logiciel (ajouter un apprenant, générer un CERFA, saisir des notes…) et je vous guide vers la bonne page.",
            'liens' => [],
            'suggestions' => BaseFaq::suggestions(),
        ];
    }

    /** Envoie la question saisie. */
    public function envoyer(): void
    {
        $texte = trim($this->question);

        if ($texte === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'texte' => $texte, 'liens' => [], 'suggestions' => []];
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

    /** Construit la réponse de l'assistant pour un message donné. */
    private function repondre(string $message): array
    {
        $resultats = BaseFaq::rechercher($message);

        if ($resultats === []) {
            return [
                'role' => 'bot',
                'texte' => "Je n'ai pas trouvé de réponse précise à cette question. Voici les sujets sur lesquels je peux vous aider :",
                'liens' => [],
                'suggestions' => BaseFaq::suggestions(),
            ];
        }

        $principal = array_shift($resultats);

        return [
            'role' => 'bot',
            'texte' => $principal['reponse'],
            'liens' => array_values(array_filter([$this->resoudreLien($principal['lien'] ?? null)])),
            // Les autres résultats deviennent des suggestions « Voir aussi » cliquables.
            'suggestions' => array_map(fn (array $e): string => $e['question'], $resultats),
        ];
    }

    /**
     * Transforme le lien d'une entrée en URL affichable, en respectant les
     * permissions : si l'utilisateur n'a pas accès au module, le lien est masqué
     * (la réponse texte, elle, reste affichée).
     */
    private function resoudreLien(?array $lien): ?array
    {
        if ($lien === null) {
            return null;
        }

        $permission = $lien['permission'] ?? null;

        if ($permission !== null && ! Auth::user()?->can($permission)) {
            return null;
        }

        return ['url' => route($lien['route']), 'label' => $lien['label']];
    }

    public function render(): View
    {
        return view('livewire.assistant-ia');
    }
}
