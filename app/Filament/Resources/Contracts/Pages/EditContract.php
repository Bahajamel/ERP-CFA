<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\ContractActions;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string
    {
        /** @var Contract $record */
        $record = $this->getRecord();

        return $record->candidate?->nom_complet
            ? 'Dossier — '.$record->candidate->nom_complet
            : 'Dossier contrat';
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ContractActions::verifierDocuments(),
                ContractActions::genererCerfa(),
                ContractActions::genererConvention(),
            ])
                ->label('Documents')
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->button()
                ->color('primary'),
            ActionGroup::make([
                ContractActions::envoyerDocumentsASigner(),
                ContractActions::deposerDocumentsSignes(),
                ContractActions::signer(),
                ContractActions::envoyerSignature(),
                ContractActions::simulerSignature(),
            ])
                ->label('Signature')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->button()
                ->color('gray'),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /* ----------------------------------------------------------------
     |  Préremplissage : charge les champs des modèles liés (candidat,
     |  entreprise, contacts, tuteur) dans l'état du formulaire à onglets.
     * ---------------------------------------------------------------- */

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Contract $record */
        $record = $this->getRecord();

        $cand = $record->candidate;
        $co = $record->company;
        $contact = $co?->contactPrincipal()->first();
        $representant = $co?->representantsLegaux()->first();
        $tuteur = $record->tuteur;
        $tuteur2 = $record->tuteur2;
        $raf = $co?->responsablesFinanciers()->first();
        $facturation = $co?->contactsFacturation()->first();

        // Onglet Contrat : les repeaters « années » sont amorcés pour les
        // anciens dossiers (1 ligne de rémunération, 3 lignes de financement).
        $data['remuneration_annuelle'] = $this->amorcerAnnees($data['remuneration_annuelle'] ?? null, 1, ['base' => 'smic']);
        $data['calendrier_financement'] = $this->amorcerAnnees($data['calendrier_financement'] ?? null, 3, []);

        return array_merge($data, [
            // Étudiant — état civil
            'etudiant_prenom' => $cand?->prenom,
            'etudiant_nom' => $cand?->nom,
            'etudiant_email' => $cand?->email,
            'etudiant_telephone' => $cand?->telephone,
            'etudiant_date_naissance' => $cand?->date_naissance?->format('Y-m-d'),
            'etudiant_sexe' => $cand?->sexe,
            'etudiant_emancipe' => $cand?->emancipe,
            'etudiant_nationalite' => $cand?->nationalite,
            'etudiant_adresse' => $cand?->adresse,
            'etudiant_code_postal' => $cand?->code_postal,
            'etudiant_ville' => $cand?->ville,
            'etudiant_pays' => $cand?->pays ?? 'France',
            'etudiant_ne_en_france' => $cand?->ne_en_france,
            'etudiant_departement_naissance' => $cand?->departement_naissance,
            'etudiant_lieu_naissance' => $cand?->lieu_naissance,
            'etudiant_num_secu' => $cand?->num_secu,
            // Étudiant — représentant légal (apprenti mineur non émancipé)
            'etudiant_repr_legal_nom' => $cand?->repr_legal_nom,
            'etudiant_repr_legal_prenom' => $cand?->repr_legal_prenom,
            'etudiant_repr_legal_email' => $cand?->repr_legal_email,
            'etudiant_repr_legal_adresse' => $cand?->repr_legal_adresse,
            'etudiant_repr_legal_complement' => $cand?->repr_legal_complement,
            'etudiant_repr_legal_code_postal' => $cand?->repr_legal_code_postal,
            'etudiant_repr_legal_ville' => $cand?->repr_legal_ville,
            // Étudiant — situation sociale
            'etudiant_rqth' => $cand?->rqth,
            'etudiant_aeeh_pch_pps' => $cand?->aeeh_pch_pps,
            'etudiant_boe' => $cand?->boe,
            'etudiant_regime_social' => $cand?->regime_social,
            'etudiant_sportif_haut_niveau' => $cand?->sportif_haut_niveau,
            'etudiant_situation_avant_contrat' => $cand?->situation_avant_contrat,
            'etudiant_projet_creation_entreprise' => $cand?->projet_creation_entreprise,
            'etudiant_formation_initiale_precedente' => $cand?->formation_initiale_precedente,
            // Étudiant — études
            'etudiant_niveau_diplome_max' => $cand?->niveau_diplome_max,
            'etudiant_diplome_max' => $cand?->diplome_max,
            'etudiant_niveau_dernier_diplome_prepare' => $cand?->niveau_dernier_diplome_prepare,
            'etudiant_dernier_diplome_prepare' => $cand?->dernier_diplome_prepare,
            'etudiant_intitule_dernier_diplome' => $cand?->intitule_dernier_diplome,
            'etudiant_derniere_classe_suivie' => $cand?->derniere_classe_suivie,
            // Entreprise
            'entreprise_raison_sociale' => $co?->raison_sociale,
            'entreprise_nom_commercial' => $co?->nom_commercial,
            'entreprise_forme_juridique' => $co?->forme_juridique,
            'entreprise_siret' => $co?->siret,
            'entreprise_siren' => $co?->siren,
            'entreprise_siret_etablissement' => $co?->siret_etablissement,
            'entreprise_ville_rcs' => $co?->ville_rcs,
            'entreprise_numero_siege' => $co?->numero_siege,
            'entreprise_adresse' => $co?->adresse,
            'entreprise_complement_adresse' => $co?->complement_adresse,
            'entreprise_code_postal' => $co?->code_postal,
            'entreprise_ville' => $co?->ville,
            'entreprise_pays' => $co?->pays ?? 'France',
            'entreprise_secteur' => $co?->secteur_type,
            'entreprise_code_ape_naf' => $co?->code_ape_naf,
            'entreprise_code_idcc' => $co?->code_idcc,
            'entreprise_convention_collective' => $co?->convention_collective,
            'entreprise_caisse_retraite' => $co?->caisse_retraite,
            'entreprise_nombre_salaries' => $co?->nombre_salaries,
            'entreprise_type_employeur' => $co?->type_employeur,
            'entreprise_type_employeur_specifique' => $co?->type_employeur_specifique,
            // Contact principal
            'contact_prenom' => $contact?->prenom,
            'contact_nom' => $contact?->nom,
            'contact_email' => $contact?->email,
            'contact_telephone' => $contact?->telephone,
            // Représentant légal
            'representant_prenom' => $representant?->prenom,
            'representant_nom' => $representant?->nom,
            'representant_email' => $representant?->email,
            'representant_poste' => $representant?->fonction,
            // Maître d'apprentissage (le lien tuteur_id reste un champ du contrat)
            'tuteur_prenom' => $tuteur?->prenom,
            'tuteur_nom' => $tuteur?->nom,
            'tuteur_email' => $tuteur?->email,
            'tuteur_telephone' => $tuteur?->telephone,
            'tuteur_fonction' => $tuteur?->fonction,
            'tuteur_date_naissance' => $tuteur?->date_naissance?->format('Y-m-d'),
            'tuteur_niveau_diplome' => $tuteur?->niveau_diplome,
            'tuteur_diplome' => $tuteur?->diplome,
            // Second maître d'apprentissage
            'tuteur2_prenom' => $tuteur2?->prenom,
            'tuteur2_nom' => $tuteur2?->nom,
            'tuteur2_email' => $tuteur2?->email,
            'tuteur2_telephone' => $tuteur2?->telephone,
            'tuteur2_fonction' => $tuteur2?->fonction,
            'tuteur2_date_naissance' => $tuteur2?->date_naissance?->format('Y-m-d'),
            'tuteur2_niveau_diplome' => $tuteur2?->niveau_diplome,
            'tuteur2_diplome' => $tuteur2?->diplome,
            // Responsable administratif et financier
            'raf_prenom' => $raf?->prenom,
            'raf_nom' => $raf?->nom,
            'raf_email' => $raf?->email,
            'raf_telephone' => $raf?->telephone,
            // Contact de facturation
            'fac_prenom' => $facturation?->prenom,
            'fac_nom' => $facturation?->nom,
            'fac_email' => $facturation?->email,
        ]);
    }

    /**
     * Amorce un repeater « par année » : renvoie les lignes existantes, ou
     * $nombre lignes vierges numérotées (annee = 1..N) pour les dossiers créés
     * avant l'ajout de ces champs. Ne remplace jamais des données saisies.
     *
     * @param  list<array<string, mixed>>|null  $lignes
     * @return list<array<string, mixed>>
     */
    private function amorcerAnnees(?array $lignes, int $nombre, array $defauts): array
    {
        if (! empty($lignes)) {
            return $lignes;
        }

        return collect(range(1, $nombre))
            ->map(fn (int $annee): array => array_merge($defauts, ['annee' => $annee]))
            ->all();
    }

    /* ----------------------------------------------------------------
     |  Sauvegarde : répartit les champs vers les modèles liés puis
     |  applique la mise à jour du contrat (avec transition de statut).
     * ---------------------------------------------------------------- */

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Contract $record */
        $this->sauvegarderCandidat($record, $data);
        $this->sauvegarderEntreprise($record, $data);
        $this->sauvegarderTuteur($record, $data);

        // On retire tous les champs des modèles liés : seuls les champs du
        // contrat sont passés à la mise à jour du contrat lui-même.
        $data = $this->sansChampsLies($data);

        // Le statut évolue via la machine à états (gardes + effets).
        $nouveauStatut = Arr::pull($data, 'statut_contrat');

        $record = parent::handleRecordUpdate($record, $data);

        if ($nouveauStatut !== null && $record->statut_contrat->value !== $nouveauStatut) {
            try {
                $record->transitionTo(ContractStatut::from($nouveauStatut));
                Notification::make()->success()
                    ->title('Statut du contrat : '.$record->statut_contrat->getLabel())->send();
            } catch (InvalidTransitionException $e) {
                Notification::make()->danger()
                    ->title('Changement de statut refusé')->body($e->getMessage())->send();
            }
        }

        return $record;
    }

    private function sauvegarderCandidat(Contract $record, array $data): void
    {
        $cand = $record->candidate;

        if (! $cand instanceof Candidate) {
            return;
        }

        $cand->forceFill(array_filter([
            'prenom' => $data['etudiant_prenom'] ?? null,
            'nom' => $data['etudiant_nom'] ?? null,
            'email' => $data['etudiant_email'] ?? null,
            'telephone' => $data['etudiant_telephone'] ?? null,
            'date_naissance' => $data['etudiant_date_naissance'] ?? null,
            'sexe' => $data['etudiant_sexe'] ?? null,
            'emancipe' => $data['etudiant_emancipe'] ?? null,
            'nationalite' => $data['etudiant_nationalite'] ?? null,
            'adresse' => $data['etudiant_adresse'] ?? null,
            'code_postal' => $data['etudiant_code_postal'] ?? null,
            'ville' => $data['etudiant_ville'] ?? null,
            'pays' => $data['etudiant_pays'] ?? null,
            'ne_en_france' => $data['etudiant_ne_en_france'] ?? null,
            'departement_naissance' => $data['etudiant_departement_naissance'] ?? null,
            'lieu_naissance' => $data['etudiant_lieu_naissance'] ?? null,
            'num_secu' => $data['etudiant_num_secu'] ?? null,
            'rqth' => $data['etudiant_rqth'] ?? null,
            'aeeh_pch_pps' => $data['etudiant_aeeh_pch_pps'] ?? null,
            'boe' => $data['etudiant_boe'] ?? null,
            'regime_social' => $data['etudiant_regime_social'] ?? null,
            'sportif_haut_niveau' => $data['etudiant_sportif_haut_niveau'] ?? null,
            'situation_avant_contrat' => $data['etudiant_situation_avant_contrat'] ?? null,
            'projet_creation_entreprise' => $data['etudiant_projet_creation_entreprise'] ?? null,
            'formation_initiale_precedente' => $data['etudiant_formation_initiale_precedente'] ?? null,
            'niveau_diplome_max' => $data['etudiant_niveau_diplome_max'] ?? null,
            'diplome_max' => $data['etudiant_diplome_max'] ?? null,
            'niveau_dernier_diplome_prepare' => $data['etudiant_niveau_dernier_diplome_prepare'] ?? null,
            'dernier_diplome_prepare' => $data['etudiant_dernier_diplome_prepare'] ?? null,
            'intitule_dernier_diplome' => $data['etudiant_intitule_dernier_diplome'] ?? null,
            'derniere_classe_suivie' => $data['etudiant_derniere_classe_suivie'] ?? null,
            'repr_legal_nom' => $data['etudiant_repr_legal_nom'] ?? null,
            'repr_legal_prenom' => $data['etudiant_repr_legal_prenom'] ?? null,
            'repr_legal_email' => $data['etudiant_repr_legal_email'] ?? null,
            'repr_legal_adresse' => $data['etudiant_repr_legal_adresse'] ?? null,
            'repr_legal_complement' => $data['etudiant_repr_legal_complement'] ?? null,
            'repr_legal_code_postal' => $data['etudiant_repr_legal_code_postal'] ?? null,
            'repr_legal_ville' => $data['etudiant_repr_legal_ville'] ?? null,
        ], fn ($v) => $v !== null))->save();
    }

    private function sauvegarderEntreprise(Contract $record, array $data): void
    {
        $co = $record->company;

        if (! $co instanceof Company) {
            return;
        }

        $co->forceFill(array_filter([
            'raison_sociale' => $data['entreprise_raison_sociale'] ?? null,
            'nom_commercial' => $data['entreprise_nom_commercial'] ?? null,
            'forme_juridique' => $data['entreprise_forme_juridique'] ?? null,
            'siret' => $data['entreprise_siret'] ?? null,
            'siren' => $data['entreprise_siren'] ?? null,
            'siret_etablissement' => $data['entreprise_siret_etablissement'] ?? null,
            'ville_rcs' => $data['entreprise_ville_rcs'] ?? null,
            'numero_siege' => $data['entreprise_numero_siege'] ?? null,
            'adresse' => $data['entreprise_adresse'] ?? null,
            'complement_adresse' => $data['entreprise_complement_adresse'] ?? null,
            'code_postal' => $data['entreprise_code_postal'] ?? null,
            'ville' => $data['entreprise_ville'] ?? null,
            'pays' => $data['entreprise_pays'] ?? null,
            'secteur_type' => $data['entreprise_secteur'] ?? null,
            'code_ape_naf' => $data['entreprise_code_ape_naf'] ?? null,
            'code_idcc' => $data['entreprise_code_idcc'] ?? null,
            'convention_collective' => $data['entreprise_convention_collective'] ?? null,
            'caisse_retraite' => $data['entreprise_caisse_retraite'] ?? null,
            'nombre_salaries' => $data['entreprise_nombre_salaries'] ?? null,
            'type_employeur' => $data['entreprise_type_employeur'] ?? null,
            'type_employeur_specifique' => $data['entreprise_type_employeur_specifique'] ?? null,
        ], fn ($v) => $v !== null))->save();

        // Contact opérationnel (is_principal) et représentant légal : réutilisés
        // s'ils existent, créés sinon, uniquement si des informations sont fournies.
        $this->majContact(
            $co,
            existant: $co->contactPrincipal()->first(),
            valeurs: [
                'prenom' => $data['contact_prenom'] ?? null,
                'nom' => $data['contact_nom'] ?? null,
                'email' => $data['contact_email'] ?? null,
                'telephone' => $data['contact_telephone'] ?? null,
            ],
            drapeau: 'is_principal',
        );

        $this->majContact(
            $co,
            existant: $co->representantsLegaux()->first(),
            valeurs: [
                'prenom' => $data['representant_prenom'] ?? null,
                'nom' => $data['representant_nom'] ?? null,
                'email' => $data['representant_email'] ?? null,
                'fonction' => $data['representant_poste'] ?? null,
            ],
            drapeau: 'is_representant_legal',
        );

        // Responsable administratif et financier.
        $this->majContact(
            $co,
            existant: $co->responsablesFinanciers()->first(),
            valeurs: [
                'prenom' => $data['raf_prenom'] ?? null,
                'nom' => $data['raf_nom'] ?? null,
                'email' => $data['raf_email'] ?? null,
                'telephone' => $data['raf_telephone'] ?? null,
            ],
            drapeau: 'is_responsable_financier',
        );

        // Contact de facturation.
        $this->majContact(
            $co,
            existant: $co->contactsFacturation()->first(),
            valeurs: [
                'prenom' => $data['fac_prenom'] ?? null,
                'nom' => $data['fac_nom'] ?? null,
                'email' => $data['fac_email'] ?? null,
            ],
            drapeau: 'is_contact_facturation',
        );
    }

    private function sauvegarderTuteur(Contract $record, array &$data): void
    {
        $this->sauvegarderMaitre($record, $data, 'tuteur', 'tuteur_id');

        // Second maître : traité uniquement si « Oui » (sinon les données restent
        // conservées mais masquées, sans écrasement brutal).
        if (! empty($data['second_maitre'])) {
            $this->sauvegarderMaitre($record, $data, 'tuteur2', 'tuteur2_id');
        }
    }

    /**
     * Met à jour (ou crée) un maître d'apprentissage rattaché à l'entreprise, à
     * partir des champs préfixés du formulaire, et fixe le lien sur le contrat.
     */
    private function sauvegarderMaitre(Contract $record, array &$data, string $prefixe, string $idField): void
    {
        $co = $record->company;

        if (! $co instanceof Company) {
            return;
        }

        $valeurs = array_filter([
            'prenom' => $data["{$prefixe}_prenom"] ?? null,
            'nom' => $data["{$prefixe}_nom"] ?? null,
            'email' => $data["{$prefixe}_email"] ?? null,
            'telephone' => $data["{$prefixe}_telephone"] ?? null,
            'fonction' => $data["{$prefixe}_fonction"] ?? null,
            'date_naissance' => $data["{$prefixe}_date_naissance"] ?? null,
            'niveau_diplome' => $data["{$prefixe}_niveau_diplome"] ?? null,
            'diplome' => $data["{$prefixe}_diplome"] ?? null,
        ], fn ($v) => filled($v));

        // Un contact est désigné : on le met à jour et on le marque maître.
        if (filled($data[$idField] ?? null)) {
            $maitre = CompanyContact::query()->whereKey($data[$idField])->where('company_id', $co->id)->first();
            if ($maitre !== null) {
                $maitre->forceFill($valeurs + ['is_tuteur' => true])->save();
            }

            return;
        }

        // Aucun contact désigné mais des coordonnées saisies : on en crée un.
        if ($valeurs !== []) {
            $maitre = $co->contacts()->create($valeurs + ['is_tuteur' => true]);
            $data[$idField] = $maitre->id;
        }
    }

    /**
     * Met à jour un contact de l'entreprise (ou le crée) avec un drapeau de rôle,
     * uniquement si au moins une information est fournie.
     *
     * @param  array<string, mixed>  $valeurs
     */
    private function majContact(Company $co, ?CompanyContact $existant, array $valeurs, string $drapeau): void
    {
        $valeurs = array_filter($valeurs, fn ($v) => filled($v));

        if ($valeurs === [] && $existant === null) {
            return;
        }

        $contact = $existant ?? $co->contacts()->make();
        $contact->forceFill($valeurs + [$drapeau => true]);
        $contact->company()->associate($co);
        $contact->save();
    }

    /** Retire du tableau tous les champs des modèles liés (préfixés). */
    private function sansChampsLies(array $data): array
    {
        foreach (array_keys($data) as $cle) {
            foreach (['etudiant_', 'entreprise_', 'contact_', 'representant_', 'raf_', 'fac_'] as $prefixe) {
                if (str_starts_with($cle, $prefixe)) {
                    unset($data[$cle]);
                }
            }
            // Champs des maîtres d'apprentissage SAUF les liens tuteur_id / tuteur2_id
            // (colonnes du contrat).
            if (str_starts_with($cle, 'tuteur_') && $cle !== 'tuteur_id') {
                unset($data[$cle]);
            }
            if (str_starts_with($cle, 'tuteur2_') && $cle !== 'tuteur2_id') {
                unset($data[$cle]);
            }
        }

        return $data;
    }
}
