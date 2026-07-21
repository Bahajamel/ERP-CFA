<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Contracts\Schemas\ContractWizard;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Support\OpcoDetector;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un dossier contrat via un assistant en 4 étapes
 * (Étudiant → Formation → Entreprise → Confirmation).
 *
 * Le dossier n'est créé qu'à la soumission finale (bouton « Créer le dossier »
 * de l'étape 4) : c'est le comportement natif du Wizard Filament. La collecte
 * est dans {@see ContractWizard} ; la création des entités et les gardes sont ici.
 *
 * Après création, on redirige vers la fiche du dossier (page d'édition) où l'on
 * complète les informations avancées et où l'on génère le CERFA / la convention.
 */
class CreateContract extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ContractResource::class;

    public function getSteps(): array
    {
        return ContractWizard::steps();
    }

    protected function getSubmitFormAction(): \Filament\Actions\Action
    {
        return parent::getSubmitFormAction()->label('Créer le dossier');
    }

    /**
     * Crée le dossier contrat et toutes ses entités liées, en une transaction :
     * candidat (retrouvé ou créé), entreprise (réutilisée ou créée), contact
     * opérationnel, représentant légal, puis le contrat lui-même. Garde
     * anti-doublon avant écriture.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Contract {
            $candidate = $this->resoudreCandidat($data);
            $company = $this->resoudreEntreprise($data);

            $this->garderContreDoublon($candidate, $company, $data);

            $this->rattacherContacts($company, $data);

            return Contract::create([
                'candidate_id' => $candidate->id,
                'company_id' => $company->id,
                'formation_id' => $data['formation_id'] ?? null,
                'type_contrat' => $data['type_contrat'] ?? null,
                'duree_formation_heures' => $data['duree_formation_heures'] ?? null,
                'nombre_organismes_formation' => $data['nombre_organismes_formation'] ?? null,
                'modalite_suivi' => $data['modalite_suivi'] ?? null,
                'heures_elearning' => $data['heures_elearning'] ?? null,
                'heures_classe_virtuelle' => $data['heures_classe_virtuelle'] ?? null,
                'cout_formation' => $data['cout_formation'] ?? null,
                'reste_a_charge_zero' => (bool) ($data['reste_a_charge_zero'] ?? false),
                'duree_diplome' => $data['duree_diplome'] ?? null,
                'annee_cycle' => $data['annee_cycle'] ?? null,
                'date_debut' => $data['date_debut'] ?? null,
                'date_fin' => $data['date_fin'] ?? null,
                // Statut initial : « En cours » (dossier créé, en préparation).
                'statut_contrat' => ContractStatut::EnCours->value,
            ]);
        });
    }

    /**
     * Retrouve le candidat par email (cloisonné au CFA) ou le crée. On ne
     * renomme jamais un candidat existant ; on ne renseigne sa formation visée
     * que si elle est absente.
     */
    private function resoudreCandidat(array $data): Candidate
    {
        $candidate = Candidate::query()->where('email', $data['etudiant_email'])->first();

        if ($candidate === null) {
            $candidate = new Candidate([
                'nom' => $data['etudiant_nom'],
                'prenom' => $data['etudiant_prenom'],
                'email' => $data['etudiant_email'],
                'formation_visee_id' => $data['formation_id'] ?? null,
            ]);
            $candidate->save();
        } elseif ($candidate->formation_visee_id === null && filled($data['formation_id'] ?? null)) {
            $candidate->forceFill(['formation_visee_id' => $data['formation_id']])->save();
        }

        if (filled($data['promotion_id'] ?? null)) {
            $candidate->promotions()->syncWithoutDetaching([$data['promotion_id']]);
        }

        return $candidate;
    }

    /**
     * Réutilise l'entreprise déjà enregistrée pour ce SIRET (restaurée si elle
     * était en corbeille, enrichie des champs légaux manquants) ou en crée une.
     */
    private function resoudreEntreprise(array $data): Company
    {
        $siret = OpcoDetector::normaliserSiret($data['entreprise_siret'] ?? null);
        $champs = $this->champsEntreprise($data, $siret);

        $existante = OpcoDetector::siretValide($siret)
            ? Company::withTrashed()->where('siret', $siret)->first()
            : null;

        if ($existante !== null) {
            if ($existante->trashed()) {
                $existante->restore();
            }

            // Enrichissement doux : ne remplit que les champs légaux restés vides.
            $aCompleter = collect($champs)
                ->only(['siren', 'siret_etablissement', 'forme_juridique', 'ville_rcs', 'numero_siege', 'complement_adresse'])
                ->filter(fn ($valeur, $cle) => filled($valeur) && blank($existante->{$cle}))
                ->all();

            if ($aCompleter !== []) {
                $existante->forceFill($aCompleter)->save();
            }

            return $existante;
        }

        return Company::create($champs);
    }

    /** @return array<string, mixed> */
    private function champsEntreprise(array $data, string $siret): array
    {
        return [
            'raison_sociale' => $data['entreprise_raison_sociale'] ?? null,
            'nom_commercial' => $data['entreprise_nom_commercial'] ?? null,
            'forme_juridique' => $data['entreprise_forme_juridique'] ?? null,
            'siret' => $siret,
            'siren' => filled($data['entreprise_siren'] ?? null)
                ? $data['entreprise_siren']
                : ContractWizard::sirenDepuisSiret($siret),
            'siret_etablissement' => $data['entreprise_siret_etablissement'] ?? null,
            'ville_rcs' => $data['entreprise_ville_rcs'] ?? null,
            'numero_siege' => $data['entreprise_numero_siege'] ?? null,
            'adresse' => $data['entreprise_adresse'] ?? null,
            'complement_adresse' => $data['entreprise_complement_adresse'] ?? null,
            'code_postal' => $data['entreprise_code_postal'] ?? null,
            'ville' => $data['entreprise_ville'] ?? null,
            'code_ape_naf' => $data['entreprise_code_ape_naf'] ?? null,
            'code_idcc' => $data['entreprise_code_idcc'] ?? null,
        ];
    }

    /**
     * Rattache le contact opérationnel (is_principal) et le représentant légal
     * (is_representant_legal) à l'entreprise. Un même email fusionne les deux
     * rôles sur un seul contact.
     */
    private function rattacherContacts(Company $company, array $data): void
    {
        // Contact opérationnel : créé seulement si au moins une info est fournie.
        if (filled($data['contact_email'] ?? null) || filled($data['contact_nom'] ?? null) || filled($data['contact_prenom'] ?? null)) {
            $contact = $this->contactParEmail($company, $data['contact_email'] ?? null);
            $contact->fill([
                'prenom' => $data['contact_prenom'] ?? $contact->prenom,
                'nom' => $data['contact_nom'] ?? $contact->nom ?? '—',
                'email' => $data['contact_email'] ?? $contact->email,
                'is_principal' => true,
            ])->save();
        }

        // Représentant légal (champs obligatoires à l'étape 3).
        $representant = $this->contactParEmail($company, $data['representant_email'] ?? null);
        $representant->fill([
            'prenom' => $data['representant_prenom'] ?? $representant->prenom,
            'nom' => $data['representant_nom'] ?? $representant->nom,
            'email' => $data['representant_email'] ?? $representant->email,
            'fonction' => $data['representant_poste'] ?? $representant->fonction,
            'is_representant_legal' => true,
        ])->save();
    }

    /**
     * Contact de l'entreprise identifié par son email (réutilisé s'il existe déjà
     * dans cette entreprise), ou nouvelle instance rattachée à l'entreprise.
     */
    private function contactParEmail(Company $company, ?string $email): CompanyContact
    {
        if (filled($email)) {
            $existant = $company->contacts()->where('email', $email)->first();

            if ($existant !== null) {
                return $existant;
            }
        }

        return $company->contacts()->make();
    }

    /**
     * Garde anti-doublon : un dossier actif existe-t-il déjà pour ce couple
     * étudiant × entreprise ? (L'invariant du modèle est candidat × entreprise ;
     * on l'anticipe ici avec un message clair plutôt qu'une exception brute.)
     */
    private function garderContreDoublon(Candidate $candidate, Company $company, array $data): void
    {
        $existant = Contract::query()
            ->where('candidate_id', $candidate->id)
            ->where('company_id', $company->id)
            ->whereNotIn('statut_contrat', [ContractStatut::Rompu->value])
            ->first();

        if ($existant === null) {
            return;
        }

        $memeFormation = filled($data['formation_id'] ?? null)
            && (int) $existant->formation_id === (int) $data['formation_id'];

        Notification::make()
            ->danger()
            ->persistent()
            ->title('Dossier contrat déjà existant')
            ->body($memeFormation
                ? 'Un dossier contrat existe déjà pour cet étudiant, cette formation et cette entreprise.'
                : 'Un dossier contrat est déjà en cours pour cet étudiant et cette entreprise. '
                    .'Ouvrez le dossier existant au lieu d\'en créer un second.')
            ->send();

        throw new Halt;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Dossier contrat créé avec succès')
            ->body('Vous pouvez maintenant compléter les informations nécessaires à la génération '
                .'du CERFA et de la convention.');
    }
}
