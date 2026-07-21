<?php

namespace App\Http\Controllers;

use App\Enums\CompanyStatut;
use App\Enums\NeedOrigine;
use App\Enums\NeedStatut;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Need;
use App\Models\Opco;
use App\Models\Organisation;
use App\Models\User;
use App\Rules\TelephoneInternational;
use App\Support\EntrepriseAnnuaire;
use App\Support\Indicatifs;
use App\Support\OpcoDetector;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Formulaire public « entreprise partenaire » (sans accès ERP) : auto-rempli
 * depuis le SIRET — identité via l'Annuaire des Entreprises, OPCO via l'API
 * officielle France Compétences (OpcoDetector). À l'envoi, une entreprise
 * « Prospect » est créée avec son contact principal.
 */
class EntrepriseFormController extends Controller
{
    public function create(): View
    {
        return view('entreprise.form', [
            'opcos' => Opco::query()->orderBy('nom')->pluck('nom', 'id'),
        ]);
    }

    /** Auto-remplissage : SIRET → identité (Annuaire) + OPCO (France Compétences). */
    public function lookup(Request $request, EntrepriseAnnuaire $annuaire, OpcoDetector $detector): JsonResponse
    {
        $siret = OpcoDetector::normaliserSiret($request->query('siret'));

        if (! OpcoDetector::siretValide($siret)) {
            return response()->json(['trouve' => false, 'message' => 'SIRET invalide (14 chiffres attendus).'], 422);
        }

        $fiche = EntrepriseAnnuaire::decode(array_key_first($annuaire->options($siret)) ?: null);
        $opco = $detector->detecter($siret);

        return response()->json([
            'trouve' => $fiche !== null,
            'raison_sociale' => $fiche['raison_sociale'] ?? null,
            'adresse' => $fiche ? trim(implode(' ', array_filter([
                $fiche['adresse'] ?? null,
                $fiche['code_postal'] ?? null,
                $fiche['ville'] ?? null,
            ]))) : null,
            'secteur' => $fiche['secteur'] ?? null,
            'opco_id' => $opco['opco']?->id,
            'opco_nom' => $opco['nom'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('entreprise.merci');
        }

        // Indicatif pays choisi + numéro → numéro international complet (validé
        // ensuite). Non assaini ci-dessous : la règle rejette d'éventuelles balises.
        $request->merge([
            'contact_telephone' => Indicatifs::combiner(
                $request->input('contact_telephone'),
                $request->input('contact_indicatif'),
            ),
        ]);

        // Assainissement des champs texte (défense en profondeur anti-XSS) :
        // balises HTML retirées, espaces normalisés, valeurs non-texte ignorées.
        $request->merge(
            collect($request->only([
                'raison_sociale', 'secteur', 'adresse',
                'contact_nom', 'contact_prenom', 'contact_email', 'contact_fonction',
            ]))
                ->map(function ($valeur) {
                    if (! is_string($valeur)) {
                        return null;
                    }

                    $propre = trim(strip_tags($valeur));

                    return $propre === '' ? null : $propre;
                })
                ->all()
        );

        // Lettres (accents compris), espaces, apostrophes, tirets — rien d'autre.
        $nomHumain = 'regex:/^[\p{L}\p{M}\s\'\’\-\.]+$/u';

        $data = $request->validate([
            'raison_sociale' => ['required', 'string', 'max:255'],
            'siret' => ['required', 'digits:14', 'unique:companies,siret'],
            'secteur' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'opco_id' => ['nullable', 'integer', 'exists:opcos,id'],
            'contact_nom' => ['required', 'string', 'max:100', $nomHumain],
            'contact_prenom' => ['nullable', 'string', 'max:100', $nomHumain],
            'contact_email' => ['nullable', 'email', 'max:255', 'required_without:contact_telephone'],
            'contact_telephone' => ['nullable', 'string', 'max:30', new TelephoneInternational, 'required_without:contact_email'],
            'contact_fonction' => ['nullable', 'string', 'max:100'],
        ], [
            'required_without' => 'Renseignez au moins un email ou un téléphone pour le contact.',
            'siret.unique' => 'Cette entreprise (SIRET) est déjà enregistrée.',
            'contact_nom.regex' => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'contact_prenom.regex' => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        ]);

        $company = DB::transaction(function () use ($data): Company {
            $company = Company::create([
                // Formulaire public (hors panel) : rattachement au CFA par défaut.
                'organisation_id' => Organisation::defaut()?->id,
                'raison_sociale' => $data['raison_sociale'],
                'siret' => $data['siret'],
                'secteur' => $data['secteur'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'opco_id' => $data['opco_id'] ?? null,
                'statut' => CompanyStatut::Prospect,
            ]);

            $company->contacts()->create([
                'nom' => $data['contact_nom'],
                'prenom' => $data['contact_prenom'] ?? null,
                'email' => $data['contact_email'] ?? null,
                'telephone' => $data['contact_telephone'] ?? null,
                'fonction' => $data['contact_fonction'] ?? null,
                'is_principal' => true,
            ]);

            return $company;
        });

        // Étape 2 : la fiche besoin. L'entreprise est retenue en session plutôt
        // qu'en clair dans l'URL — rien de nouveau n'est exposé publiquement, et
        // la durée de vie de session sert d'expiration.
        session([self::CLE_SESSION => $company->id]);

        return redirect()->route('entreprise.besoin');
    }

    /* ----------------------------------------------------------------
     |  Étape 2 — fiche besoin (l'entreprise décrit le poste recherché).
     * ---------------------------------------------------------------- */

    /** Entreprise en cours de parcours (étape 1 validée), retenue en session. */
    private const CLE_SESSION = 'entreprise_partenaire_id';

    public function besoin(Request $request): View|RedirectResponse
    {
        $company = $this->entrepriseDuParcours($request);

        if ($company === null) {
            return redirect()->route('entreprise.create')
                ->with('expire', 'Votre session a expiré. Merci de renseigner à nouveau votre entreprise.');
        }

        return view('entreprise.besoin', [
            'company' => $company,
            'formations' => Formation::query()
                ->where('organisation_id', $company->organisation_id)
                ->orderBy('libelle')
                ->pluck('libelle', 'id'),
        ]);
    }

    public function besoinStore(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('entreprise.merci');
        }

        $company = $this->entrepriseDuParcours($request);

        if ($company === null) {
            return redirect()->route('entreprise.create')
                ->with('expire', 'Votre session a expiré. Merci de renseigner à nouveau votre entreprise.');
        }

        // Assainissement des champs texte (même défense en profondeur qu'à l'étape 1).
        $request->merge(
            collect($request->only(['intitule_poste', 'rythme', 'localisation', 'prerequis']))
                ->map(function ($valeur) {
                    if (! is_string($valeur)) {
                        return null;
                    }

                    $propre = trim(strip_tags($valeur));

                    return $propre === '' ? null : $propre;
                })
                ->all()
        );

        $data = $request->validate([
            'intitule_poste' => ['required', 'string', 'max:255'],
            'formation_id' => ['nullable', 'integer', 'exists:formations,id'],
            'nb_postes' => ['required', 'integer', 'min:1', 'max:99'],
            'date_demarrage' => ['nullable', 'date', 'after_or_equal:today'],
            'rythme' => ['nullable', 'string', 'max:255'],
            'localisation' => ['nullable', 'string', 'max:255'],
            'prerequis' => ['nullable', 'string', 'max:5000'],
        ], [
            'intitule_poste.required' => 'Indiquez l\'intitulé du poste recherché.',
            'date_demarrage.after_or_equal' => 'La date de démarrage ne peut pas être dans le passé.',
        ]);

        $need = Need::create([
            // Hors contexte CFA : on rattache le besoin au même CFA que l'entreprise.
            'organisation_id' => $company->organisation_id,
            'company_id' => $company->id,
            // contactPrincipal() est un HasMany malgré son nom : on prend le premier.
            'contact_id' => $company->contacts()->where('is_principal', true)->value('id'),
            'intitule_poste' => $data['intitule_poste'],
            'formation_id' => $data['formation_id'] ?? null,
            'nb_postes' => $data['nb_postes'],
            'date_demarrage' => $data['date_demarrage'] ?? null,
            'rythme' => $data['rythme'] ?? null,
            'localisation' => $data['localisation'] ?? $company->adresse,
            'prerequis' => $data['prerequis'] ?? null,
            'statut' => NeedStatut::Cree,
            // Déposée par l'entreprise : reste hors des offres actives tant qu'un
            // commercial ne l'a pas relue (cf. Need::scopeOuverts).
            'origine' => NeedOrigine::Entreprise,
        ]);

        $this->notifierCommerciaux($need, $company);

        $request->session()->forget(self::CLE_SESSION);

        return redirect()->route('entreprise.merci')->with('besoin_depose', true);
    }

    /** Entreprise de l'étape 1, ou null si la session a expiré / a été vidée. */
    private function entrepriseDuParcours(Request $request): ?Company
    {
        $id = $request->session()->get(self::CLE_SESSION);

        return $id === null ? null : Company::query()->find($id);
    }

    /**
     * Prévient les commerciaux du CFA qu'un besoin attend leur relecture
     * (notification en base, visible dans la cloche de l'ERP).
     */
    private function notifierCommerciaux(Need $need, Company $company): void
    {
        $destinataires = User::query()
            ->when(
                $company->organisation_id !== null,
                fn ($q) => $q->whereHas('organisations', fn ($o) => $o->whereKey($company->organisation_id)),
            )
            ->get()
            ->filter(fn (User $user): bool => $user->can('access_needs'));

        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Nouveau besoin à valider : '.$need->intitule_poste)
            ->body($company->raison_sociale.' vient de déposer un besoin via le formulaire entreprise.')
            ->icon('heroicon-o-inbox-arrow-down')
            ->info()
            ->sendToDatabase($destinataires);
    }
}
