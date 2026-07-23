<?php

namespace App\Livewire;

use App\Enums\DemoRequestStatut;
use App\Mail\NouvelleDemandeDemo;
use App\Models\DemoRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formulaire public « Demander une démonstration » du site vitrine.
 *
 * Enregistre un prospect (DemoRequest) et prévient l'équipe éditeur. Aucun CFA
 * n'est créé : l'ouverture d'un compte reste un acte commercial décidé depuis
 * le panneau /editeur.
 *
 * Défenses reprises des autres formulaires publics du projet : pot de miel
 * (champ « website » invisible) + limitation de fréquence par IP.
 */
class DemandeDemoForm extends Component
{
    /** Tranches proposées (valeurs = libellés, stockées telles quelles). */
    public const TRANCHES = ['1 à 50', '50 à 200', '200 à 500', 'Plus de 500'];

    public const BESOINS = [
        'Gestion des candidats',
        'Entreprises et alternance',
        'Contrats et OPCO',
        'Scolarité et pédagogie',
        'Finance et facturation',
        'Pilotage global',
    ];

    /** Nombre d'envois autorisés par IP et par heure. */
    private const MAX_PAR_HEURE = 5;

    public string $first_name = '';

    public string $last_name = '';

    public string $job_title = '';

    public string $organization_name = '';

    public string $email = '';

    public string $phone = '';

    public string $learner_count = '';

    public string $main_need = '';

    public string $message = '';

    public bool $consent = false;

    /** Pot de miel : invisible pour un humain, rempli par les robots. */
    public string $website = '';

    /** Passe à vrai après enregistrement : la vue affiche la confirmation. */
    public bool $envoye = false;

    protected function rules(): array
    {
        // Lettres (accents compris), espaces, apostrophes, tirets — rien d'autre.
        $nomHumain = 'regex:/^[\p{L}\p{M}\s\'\’\-\.]+$/u';

        return [
            'first_name' => ['required', 'string', 'max:100', $nomHumain],
            'last_name' => ['required', 'string', 'max:100', $nomHumain],
            'job_title' => ['nullable', 'string', 'max:100'],
            'organization_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'learner_count' => ['nullable', Rule::in(self::TRANCHES)],
            'main_need' => ['nullable', Rule::in(self::BESOINS)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'job_title' => 'fonction',
            'organization_name' => 'nom de l\'établissement',
            'email' => 'adresse e-mail',
            'phone' => 'téléphone',
            'learner_count' => 'nombre d\'apprenants',
            'main_need' => 'besoin principal',
        ];
    }

    protected function messages(): array
    {
        return [
            'consent.accepted' => 'Merci d\'accepter que vos informations soient utilisées pour vous recontacter.',
            'first_name.regex' => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'last_name.regex' => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'email.email' => 'Saisissez une adresse e-mail valide (avec @).',
        ];
    }

    /** Validation au fil de la saisie, pour un retour immédiat. */
    public function updated(string $champ): void
    {
        if ($champ !== 'website') {
            $this->validateOnly($champ);
        }
    }

    public function envoyer(): void
    {
        // Pot de miel : on affiche la confirmation sans rien enregistrer, pour
        // ne pas renseigner le robot sur la détection.
        if (filled($this->website)) {
            $this->envoye = true;

            return;
        }

        $donnees = $this->validate();

        if ($this->tropDeTentatives()) {
            $this->addError('email', 'Trop de demandes envoyées depuis cette adresse. Réessayez dans quelques minutes.');

            return;
        }

        $demande = DemoRequest::create([
            'first_name' => $donnees['first_name'],
            'last_name' => $donnees['last_name'],
            'job_title' => $this->nettoyer($donnees['job_title'] ?? null),
            'organization_name' => $this->nettoyer($donnees['organization_name']),
            'email' => $donnees['email'],
            'phone' => $this->nettoyer($donnees['phone'] ?? null),
            'learner_count' => $donnees['learner_count'] ?: null,
            'main_need' => $donnees['main_need'] ?: null,
            'message' => $this->nettoyer($donnees['message'] ?? null),
            'status' => DemoRequestStatut::Nouveau,
            'consent_at' => now(),
            'ip' => request()->ip(),
        ]);

        $this->prevenirEquipe($demande);

        $this->envoye = true;
    }

    /** Limite par IP : protège d'un envoi en boucle sans gêner un usage normal. */
    private function tropDeTentatives(): bool
    {
        $cle = 'demande-demo:'.request()->ip();

        if (RateLimiter::tooManyAttempts($cle, self::MAX_PAR_HEURE)) {
            return true;
        }

        RateLimiter::hit($cle, 3600);

        return false;
    }

    /** Retire les balises HTML (défense en profondeur), null si vide. */
    private function nettoyer(?string $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $propre = trim(strip_tags($valeur));

        return $propre === '' ? null : $propre;
    }

    /**
     * Prévient l'équipe éditeur : notification dans la cloche de l'ERP + e-mail.
     *
     * L'envoi d'e-mail est isolé : une configuration mail absente ou en panne ne
     * doit jamais faire échouer la demande du prospect, déjà enregistrée.
     */
    private function prevenirEquipe(DemoRequest $demande): void
    {
        $destinataires = User::query()
            ->get()
            ->filter(fn (User $user): bool => $user->can('access_editeur'));

        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Nouvelle demande de démonstration')
            ->body($demande->nomComplet().' — '.$demande->organization_name)
            ->icon('heroicon-o-presentation-chart-line')
            ->info()
            ->sendToDatabase($destinataires);

        try {
            Mail::to($destinataires->pluck('email')->all())->send(new NouvelleDemandeDemo($demande));
        } catch (\Throwable $e) {
            Log::warning('Demande de démo enregistrée mais e-mail non envoyé : '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.demande-demo-form');
    }
}
