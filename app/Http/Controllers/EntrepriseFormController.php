<?php

namespace App\Http\Controllers;

use App\Enums\CompanyStatut;
use App\Models\Company;
use App\Models\Opco;
use App\Rules\TelephoneInternational;
use App\Support\EntrepriseAnnuaire;
use App\Support\Indicatifs;
use App\Support\OpcoDetector;
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

        DB::transaction(function () use ($data): void {
            $company = Company::create([
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
        });

        return redirect()->route('entreprise.merci');
    }
}
