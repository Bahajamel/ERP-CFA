<?php

namespace App\Http\Controllers;

use App\Enums\CandidateStatut;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Candidate;
use App\Models\Formation;
use App\Rules\TelephoneInternational;
use App\Support\CfaPublic;
use App\Support\Indicatifs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Formulaire public de candidature (sans accès à l'ERP) : le candidat saisit ses
 * informations et dépose ses pièces obligatoires. À l'envoi, un candidat est créé
 * dans l'ERP (statut « Entretien à planifier »).
 *
 * Chaque pièce est stockée à deux titres, sans re-téléverser le fichier :
 *  1. dans la collection média dédiée du candidat (cv, piece_identite,
 *     carte_vitale, attestation_projet) — utilisée par la fiche, le CERFA, l'OPCO ;
 *  2. comme document GED rattaché au candidat (copie du même fichier), pour
 *     apparaître dans la section « Documents » du profil, typé et daté.
 */
class CandidatureController extends Controller
{
    /** Type de document GED associé à chaque pièce du formulaire public. */
    private const TYPES_DOCUMENT = [
        'cv' => DocumentType::CvCandidat,
        'piece_identite' => DocumentType::PieceIdentite,
        'carte_vitale' => DocumentType::CarteVitale,
        'attestation_projet' => DocumentType::Autre,
    ];

    public function create(?string $cfa = null): View
    {
        // CFA destinataire : segment d'URL, sinon CFA par défaut (lien historique).
        $organisation = CfaPublic::resoudre($cfa);

        return view('candidature.form', [
            'cfa' => $organisation,
            // Le catalogue proposé est celui du CFA visé : sans ce filtre, un
            // candidat verrait les formations de tous les CFA de la plateforme.
            //
            // tousLesCfa() puis filtre explicite : une page publique ne doit pas
            // dépendre du tenant ambiant (nul en production, mais pas forcément
            // dans un test ou un futur middleware) — le CFA vient de l'URL, point.
            'formations' => Formation::query()
                ->tousLesCfa()
                ->when($organisation !== null, fn ($q) => $q->where('organisation_id', $organisation->id))
                ->orderBy('libelle')
                ->pluck('libelle', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('candidature.merci');
        }

        $data = $this->valider($request);

        // CFA destinataire : celui de la page réellement remplie (champ caché),
        // sinon le CFA par défaut.
        $cfa = CfaPublic::depuisRequete($request);

        DB::transaction(function () use ($data, $request, $cfa): void {
            $candidate = Candidate::create([
                // Formulaire public (hors panel) : Filament n'a pas de tenant ici,
                // le rattachement au CFA est donc explicite.
                'organisation_id' => $cfa?->id,
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'] ?? null,
                'telephone' => $data['telephone'] ?? null,
                'date_naissance' => $data['date_naissance'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'formation_visee_id' => $data['formation_visee_id'] ?? null,
                'statut' => CandidateStatut::EntretienAPlanifier,
                'source' => 'Candidature en ligne',
                // Consentement RGPD à la transmission du CV aux entreprises (case cochée).
                'cv_consentement' => $request->boolean('cv_consentement'),
            ]);

            $this->attacher($candidate, $request->file('cv'), 'cv');
            $this->attacher($candidate, $request->file('piece_identite'), 'piece_identite');
            $this->attacher($candidate, $request->file('carte_vitale'), 'carte_vitale');
            $this->attacher($candidate, $request->file('attestation_projet'), 'attestation_projet');
        });

        // Le slug suit jusqu'à la page de fin : « déposer une autre candidature »
        // doit revenir au MÊME CFA, pas au CFA par défaut.
        return redirect()->route('candidature.merci')->with('cfa_slug', $cfa?->slug);
    }

    /**
     * Attache une pièce à sa collection média, puis en trace une copie dans la
     * GED du candidat (section Documents) — typée, datée, téléchargeable.
     */
    private function attacher(Candidate $candidate, ?UploadedFile $fichier, string $collection): void
    {
        if (! $fichier instanceof UploadedFile) {
            return;
        }

        $media = $candidate->addMedia($fichier)->toMediaCollection($collection);

        $this->tracerDansGed($candidate, $media, self::TYPES_DOCUMENT[$collection] ?? DocumentType::Autre);
    }

    /**
     * Crée le document GED correspondant à une pièce déposée et y recopie le
     * fichier déjà stocké (aucun nouveau téléversement, aucune perte).
     */
    private function tracerDansGed(Candidate $candidate, Media $media, DocumentType $type): void
    {
        $document = $candidate->documents()->create([
            // Le formulaire public tourne hors contexte CFA : le trait
            // BelongsToOrganisation ne peut pas déduire le tenant, donc on aligne
            // explicitement le document sur le CFA du candidat (comme à sa création).
            // Sans cela le document naît à organisation_id nul → invisible du panneau
            // (le candidat paraît alors sans aucune pièce).
            'organisation_id' => $candidate->organisation_id,
            'type' => $type->value,
            'statut' => DocumentStatut::Recu->value,
            'source' => DocumentSource::Candidature->value,
            'nom_fichier' => $type->getLabel(),
        ]);

        $media->copy($document, 'fichier');
    }

    /**
     * Nettoie les champs texte d'un formulaire public : balises HTML retirées
     * (défense en profondeur contre le XSS stocké), espaces normalisés.
     * Blade échappe déjà à l'affichage — on refuse en plus d'en stocker.
     */
    private function assainir(Request $request, array $champs): array
    {
        return collect($request->only($champs))
            ->map(function ($valeur) {
                if (! is_string($valeur)) {
                    return null; // tableaux/objets injectés → ignorés
                }

                $propre = trim(strip_tags($valeur));

                return $propre === '' ? null : $propre;
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        // Indicatif pays choisi + numéro → numéro international complet (validé
        // ensuite). Non assaini : la règle rejette d'éventuelles balises.
        $request->merge([
            'telephone' => Indicatifs::combiner($request->input('telephone'), $request->input('indicatif_pays')),
        ]);

        $request->merge($this->assainir($request, ['nom', 'prenom', 'email', 'adresse']));

        // Lettres (accents compris), espaces, apostrophes, tirets — rien d'autre.
        $nomHumain = ['regex:/^[\p{L}\p{M}\s\'\’\-\.]+$/u'];

        $justificatif = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        $cv = ['file', 'mimes:pdf,doc,docx', 'max:5120'];

        $validator = validator($request->all(), [
            'nom' => array_merge(['required', 'string', 'max:100'], $nomHumain),
            'prenom' => array_merge(['required', 'string', 'max:100'], $nomHumain),
            'email' => ['required', 'email', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', new TelephoneInternational],
            'date_naissance' => ['required', 'date', 'before:today'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'formation_visee_id' => ['required', 'integer', 'exists:formations,id'],
            'piece_identite' => array_merge(['required'], $justificatif),
            'cv' => array_merge(['required'], $cv),
            'carte_vitale' => array_merge(['required'], $justificatif),
            'attestation_projet' => array_merge(['nullable'], $justificatif),
        ], [
            'nom.regex' => 'Le nom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
            'prenom.regex' => 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.',
        ], [
            'formation_visee_id' => 'formation visée',
            'piece_identite' => "pièce d'identité",
            'carte_vitale' => 'carte vitale',
            'attestation_projet' => 'attestation de création de projet',
        ]);

        // Attestation de création de projet obligatoire pour les candidats de 30 ans ou plus.
        $validator->after(function ($validator) use ($request): void {
            if (Candidate::dateNaissancePlusDe30Ans($request->input('date_naissance'))
                && ! $request->hasFile('attestation_projet')) {
                $validator->errors()->add(
                    'attestation_projet',
                    'L\'attestation de création de projet est obligatoire à partir de 30 ans.'
                );
            }
        });

        return $validator->validate();
    }
}
