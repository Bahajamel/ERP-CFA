<?php

namespace App\Parcours;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Enums\OpcoStatut;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Matching;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Models\Rupture;
use App\Support\OpcoDetector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service central du cycle de vie apprenant :
 *
 *   Candidat (accepté) → Matching (accepté) → Contrat (signé 3 parties)
 *     → Dossier OPCO (créé/transmis) → Admission officielle (« À vérifier »)
 *     → éventuellement Rupture (livrables).
 *
 * Toute la logique de passage d'étape vit ici : prérequis, anti-doublons,
 * création automatique de l'étape suivante et messages métier. Les modèles
 * portent en miroir des invariants bloquants (création/transition) pour que
 * les règles tiennent aussi hors interface.
 */
class CycleApprenant
{
    /** Messages métier des blocages du cycle (affichés tels quels). */
    public const MSG_CANDIDAT_NON_ACCEPTE = 'Impossible d\'envoyer ce candidat au Matching : le candidat n\'est pas accepté.';

    public const MSG_MATCHING_EXISTANT = 'Un matching existe déjà pour ce candidat et ce besoin.';

    public const MSG_MATCHING_NON_ACCEPTE = 'Impossible de créer un contrat : le matching n\'est pas accepté.';

    public const MSG_CONTRAT_NON_SIGNE = 'Le dossier OPCO ne peut pas être créé tant que le contrat n\'est pas signé par les trois parties.';

    public const MSG_OPCO_MANQUANT = 'Impossible de créer une admission : le dossier OPCO n\'est pas encore créé ou transmis.';

    public const MSG_RETOUR_ENTRETIEN = 'Impossible de revenir au statut « Entretien prévu » après une décision finale.';

    /** Origines d'un matching : proposé par le CFA ou entreprise trouvée par le candidat. */
    public const ORIGINE_CFA = 'cfa';

    public const ORIGINE_CANDIDAT = 'candidat';

    /** Statuts matching considérés comme « en cours » (anti-doublon, timeline). */
    public const MATCHING_ACTIFS = [
        MatchingStatut::EnRecherche,
        MatchingStatut::PropositionEnvoyee,
        MatchingStatut::EntretienEntreprise,
    ];

    /** Statuts contrat considérés comme actifs (anti-doublon candidat × entreprise). */
    public const CONTRATS_ACTIFS_EXCLUS = [ContractStatut::Rompu, ContractStatut::Archive];

    /**
     * Le dossier OPCO vaut-il « créé ou transmis pour validation » ?
     * (Tout état au-delà de la préparation ouvre l'admission officielle —
     * un refus OPCO ultérieur ne la supprime jamais.)
     */
    public static function opcoOuvreAdmission(OpcoStatut $statut): bool
    {
        return ! in_array($statut, [OpcoStatut::NonCree, OpcoStatut::APreparer], true);
    }

    /* ----------------------------------------------------------------
     |  Candidat → Matching
     * ---------------------------------------------------------------- */

    /**
     * Envoie un candidat accepté vers le Matching sur un besoin entreprise.
     *
     * @throws CycleBloqueException candidat non accepté ou matching déjà existant
     */
    public function envoyerVersMatching(Candidate $candidate, Need $need, string $origine = self::ORIGINE_CFA): Matching
    {
        if ($candidate->statut !== CandidateStatut::Accepte) {
            throw new CycleBloqueException(self::MSG_CANDIDAT_NON_ACCEPTE);
        }

        if (Matching::query()->where('need_id', $need->id)->where('candidate_id', $candidate->id)->exists()) {
            throw new CycleBloqueException(self::MSG_MATCHING_EXISTANT);
        }

        return Matching::query()->create([
            'need_id' => $need->id,
            'candidate_id' => $candidate->id,
            'origine' => $origine,
            'statut' => MatchingStatut::EnRecherche->value,
            'assigned_by' => Auth::id(),
        ]);
    }

    /**
     * Cas « entreprise externe » : le candidat a trouvé lui-même une
     * entreprise qui n'est pas encore partenaire. L'entreprise est créée
     * (ou retrouvée par SIRET) comme prospect à qualifier, un besoin
     * minimal est ouvert, et le matching est tracé origine « candidat ».
     *
     * @param  array{raison_sociale: string, siret: string, intitule_poste?: ?string}  $entreprise
     *
     * @throws CycleBloqueException
     */
    public function entrepriseTrouveeParCandidat(Candidate $candidate, array $entreprise): Matching
    {
        if ($candidate->statut !== CandidateStatut::Accepte) {
            throw new CycleBloqueException(self::MSG_CANDIDAT_NON_ACCEPTE);
        }

        return DB::transaction(function () use ($candidate, $entreprise): Matching {
            $company = Company::query()->firstOrCreate(
                ['siret' => OpcoDetector::normaliserSiret($entreprise['siret'])],
                [
                    'raison_sociale' => $entreprise['raison_sociale'],
                    // Entreprise externe : prospect à qualifier par le CFA.
                    'statut' => CompanyStatut::Prospect->value,
                ],
            );

            $poste = filled($entreprise['intitule_poste'] ?? null)
                ? $entreprise['intitule_poste']
                : 'Poste apporté par '.$candidate->nom_complet;

            $need = Need::query()->firstOrCreate(
                ['company_id' => $company->id, 'intitule_poste' => $poste],
                [
                    'formation_id' => $candidate->formation_visee_id,
                    'statut' => NeedStatut::Cree->value,
                ],
            );

            if (Matching::query()->where('need_id', $need->id)->where('candidate_id', $candidate->id)->exists()) {
                throw new CycleBloqueException(self::MSG_MATCHING_EXISTANT);
            }

            return Matching::query()->create([
                'need_id' => $need->id,
                'candidate_id' => $candidate->id,
                'origine' => self::ORIGINE_CANDIDAT,
                'statut' => MatchingStatut::EnRecherche->value,
                'assigned_by' => Auth::id(),
            ]);
        });
    }

    /* ----------------------------------------------------------------
     |  Matching accepté → Contrat
     * ---------------------------------------------------------------- */

    /**
     * Crée (ou retrouve) le contrat issu d'un matching accepté, prérempli
     * avec le candidat, l'entreprise, la formation et le tuteur du besoin.
     * Anti-doublon : un seul contrat actif par candidat × entreprise — le
     * contrat existant est retourné (`wasRecentlyCreated` = false).
     *
     * @throws CycleBloqueException matching non accepté
     */
    public function creerContratDepuisMatching(Matching $matching): Contract
    {
        if ($matching->statut !== MatchingStatut::Accepte) {
            throw new CycleBloqueException(self::MSG_MATCHING_NON_ACCEPTE);
        }

        $need = $matching->need;
        $candidate = $matching->candidate;

        $existant = Contract::query()
            ->where('candidate_id', $candidate->id)
            ->where('company_id', $need->company_id)
            ->whereNotIn('statut_contrat', array_map(fn (ContractStatut $s) => $s->value, self::CONTRATS_ACTIFS_EXCLUS))
            ->first();

        if ($existant !== null) {
            return $existant;
        }

        $formation = $need->formation ?? $candidate->formationVisee;

        return Contract::query()->create([
            'candidate_id' => $candidate->id,
            'company_id' => $need->company_id,
            'formation_id' => $formation?->id,
            'tuteur_id' => $need->tuteur_id,
            'code_rncp' => $formation?->code_rncp,
            'rythme' => $need->rythme,
            'date_debut' => $need->date_demarrage,
            'statut_contrat' => ContractStatut::Brouillon->value,
        ]);
    }

    /* ----------------------------------------------------------------
     |  OPCO créé/transmis → Admission officielle
     * ---------------------------------------------------------------- */

    /**
     * Ouvre l'admission officielle dès que le dossier OPCO est créé ou
     * transmis pour validation. Idempotent : une seule admission par
     * contrat, toujours au statut initial « À vérifier ». Retourne null
     * tant que le dossier OPCO n'a pas atteint ce stade.
     */
    public function ouvrirAdmission(OpcoFile $dossier): ?Admission
    {
        $contract = $dossier->contract;

        if ($contract === null || ! self::opcoOuvreAdmission($dossier->statut)) {
            return null;
        }

        return Admission::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'candidate_id' => $contract->candidate_id,
                'statut' => AdmissionStatut::AVerifier->value,
            ],
        );
    }

    /* ----------------------------------------------------------------
     |  Admission → Rupture
     * ---------------------------------------------------------------- */

    /**
     * Ouvre (ou retrouve) le dossier de rupture lié à une admission.
     * Idempotent : une seule rupture par contrat. La création propage les
     * traces (contrat → Rompu, admission → Rupture) via le modèle Rupture.
     *
     * @param  array{date_rupture?: ?string, motif?: ?string, initiative?: ?string, commentaire?: ?string}  $attributs
     *
     * @throws CycleBloqueException admission sans contrat rattaché
     */
    public function ouvrirRupture(Admission $admission, array $attributs = []): Rupture
    {
        $contract = $admission->contract;

        if ($contract === null) {
            throw new CycleBloqueException('Impossible de déclarer une rupture : aucune admission officielle liée à un contrat.');
        }

        return Rupture::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            array_filter($attributs, fn ($v) => filled($v)) + [
                'date_rupture' => now()->toDateString(),
                'motif' => RuptureMotif::Autre->value,
                'statut' => RuptureStatut::ATraiter->value,
                'created_by' => Auth::id(),
            ],
        );
    }

    /* ----------------------------------------------------------------
     |  Timeline du parcours
     * ---------------------------------------------------------------- */

    public const ETAT_TERMINEE = 'terminee';

    public const ETAT_EN_COURS = 'en_cours';

    public const ETAT_BLOQUEE = 'bloquee';

    public const ETAT_NON_DEMARREE = 'non_demarree';

    /**
     * Parcours de l'apprenant, étape par étape, pour la timeline des fiches :
     * chaque étape porte un état (terminée / en cours / bloquée / non
     * démarrée) et un détail lisible.
     *
     * @return list<array{cle: string, libelle: string, etat: string, detail: string}>
     */
    public function etapes(Candidate $candidate): array
    {
        $contrat = $candidate->contracts()
            ->where('statut_contrat', '!=', ContractStatut::Archive->value)
            ->latest('id')
            ->first();

        $opco = $contrat?->opcoFile;
        $admission = $contrat?->admission
            ?? Admission::query()->where('candidate_id', $candidate->id)->latest('id')->first();
        $rupture = $contrat?->rupture;

        $matchingAccepte = $candidate->matchings()->where('statut', MatchingStatut::Accepte->value)->exists();
        $matchingActif = $candidate->matchings()
            ->whereIn('statut', array_map(fn (MatchingStatut $s) => $s->value, self::MATCHING_ACTIFS))
            ->exists();

        $refuse = $candidate->statut === CandidateStatut::Refuse;

        $etapes = [];

        $etapes[] = [
            'cle' => 'candidat',
            'libelle' => 'Candidat',
            'etat' => match ($candidate->statut) {
                CandidateStatut::Accepte => self::ETAT_TERMINEE,
                CandidateStatut::Refuse => self::ETAT_BLOQUEE,
                default => self::ETAT_EN_COURS,
            },
            'detail' => $candidate->statut->getLabel(),
        ];

        $etapes[] = [
            'cle' => 'matching',
            'libelle' => 'Matching',
            'etat' => match (true) {
                $refuse => self::ETAT_BLOQUEE,
                $matchingAccepte || $contrat !== null => self::ETAT_TERMINEE,
                $matchingActif => self::ETAT_EN_COURS,
                default => self::ETAT_NON_DEMARREE,
            },
            'detail' => match (true) {
                $refuse => 'Candidat refusé',
                $matchingAccepte => 'Entreprise trouvée',
                $contrat !== null => 'Entreprise trouvée',
                $matchingActif => 'Recherche en cours',
                default => 'Non démarré',
            },
        ];

        $etapes[] = [
            'cle' => 'contrat',
            'libelle' => 'Contrat',
            'etat' => match (true) {
                $contrat?->estSigne() ?? false => self::ETAT_TERMINEE,
                $contrat !== null => self::ETAT_EN_COURS,
                default => self::ETAT_NON_DEMARREE,
            },
            'detail' => $contrat !== null
                ? ($contrat->estSigne() ? 'Signé par les trois parties' : $contrat->statut_contrat->getLabel())
                : 'Non démarré',
        ];

        $etapes[] = [
            'cle' => 'opco',
            'libelle' => 'Dossier OPCO',
            'etat' => match (true) {
                $opco !== null && self::opcoOuvreAdmission($opco->statut) => self::ETAT_TERMINEE,
                $opco !== null => self::ETAT_EN_COURS,
                default => self::ETAT_NON_DEMARREE,
            },
            'detail' => $opco?->statut->getLabel() ?? 'Non démarré',
        ];

        $etapes[] = [
            'cle' => 'admission',
            'libelle' => 'Admission',
            'etat' => match ($admission?->statut) {
                AdmissionStatut::Valide => self::ETAT_TERMINEE,
                AdmissionStatut::AVerifier => self::ETAT_EN_COURS,
                AdmissionStatut::Rupture => self::ETAT_BLOQUEE,
                default => self::ETAT_NON_DEMARREE,
            },
            'detail' => $admission?->statut->getLabel() ?? 'Non démarré',
        ];

        $etapes[] = [
            'cle' => 'rupture',
            'libelle' => 'Rupture',
            'etat' => match (true) {
                $rupture?->statut === RuptureStatut::Cloturee => self::ETAT_TERMINEE,
                $rupture !== null => self::ETAT_EN_COURS,
                $admission?->statut === AdmissionStatut::Rupture => self::ETAT_EN_COURS,
                default => self::ETAT_NON_DEMARREE,
            },
            'detail' => $rupture?->statut->getLabel() ?? 'Aucune',
        ];

        return $etapes;
    }

    /** Étape courante du parcours (première non terminée), pour les badges. */
    public function etapeCourante(Candidate $candidate): array
    {
        $etapes = $this->etapes($candidate);

        foreach ($etapes as $etape) {
            if (in_array($etape['etat'], [self::ETAT_EN_COURS, self::ETAT_BLOQUEE], true)) {
                return $etape;
            }
        }

        foreach (array_reverse($etapes) as $etape) {
            if ($etape['etat'] === self::ETAT_TERMINEE) {
                return $etape;
            }
        }

        return $etapes[0];
    }
}
