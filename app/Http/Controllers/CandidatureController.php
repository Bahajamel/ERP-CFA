<?php

namespace App\Http\Controllers;

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\Models\Formation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Formulaire public de candidature (sans accès à l'ERP) : le candidat saisit ses
 * informations et dépose ses pièces obligatoires. À l'envoi, un candidat est créé
 * dans l'ERP (statut « Dossier incomplet »), pièces rattachées à ses collections
 * média (cv, piece_identite, carte_vitale, attestation_projet).
 */
class CandidatureController extends Controller
{
    public function create(): View
    {
        return view('candidature.form', [
            'formations' => Formation::query()->orderBy('libelle')->pluck('libelle', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('candidature.merci');
        }

        $data = $this->valider($request);

        DB::transaction(function () use ($data, $request): void {
            $candidate = Candidate::create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'] ?? null,
                'telephone' => $data['telephone'] ?? null,
                'date_naissance' => $data['date_naissance'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'formation_visee_id' => $data['formation_visee_id'] ?? null,
                'statut' => CandidateStatut::EntretienPrevu,
                'source' => 'Candidature en ligne',
            ]);

            $this->attacher($candidate, $request->file('cv'), 'cv');
            $this->attacher($candidate, $request->file('piece_identite'), 'piece_identite');
            $this->attacher($candidate, $request->file('carte_vitale'), 'carte_vitale');
            $this->attacher($candidate, $request->file('attestation_projet'), 'attestation_projet');
        });

        return redirect()->route('candidature.merci');
    }

    private function attacher(Candidate $candidate, ?UploadedFile $fichier, string $collection): void
    {
        if ($fichier instanceof UploadedFile) {
            $candidate->addMedia($fichier)->toMediaCollection($collection);
        }
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        $justificatif = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        $cv = ['file', 'mimes:pdf,doc,docx', 'max:5120'];

        $validator = validator($request->all(), [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:telephone'],
            'telephone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'date_naissance' => ['required', 'date', 'before:today'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'formation_visee_id' => ['required', 'integer', 'exists:formations,id'],
            'piece_identite' => array_merge(['required'], $justificatif),
            'cv' => array_merge(['required'], $cv),
            'carte_vitale' => array_merge(['required'], $justificatif),
            'attestation_projet' => array_merge(['nullable'], $justificatif),
        ], [
            'required_without' => 'Renseignez au moins un email ou un téléphone.',
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
