<?php

namespace App\Parcours;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\CompanyStatut;
use App\Enums\ContractStatut;
use App\Enums\EntretienStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Enums\OpcoStatut;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Entretien;
use App\Models\Matching;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Models\Rupture;
use App\Support\OpcoDetector;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service central du cycle de vie apprenant :
 *
 *   Candidat → Entretien (réalisé + accepté) → Matching (accepté)
 *     → Contrat (signé 3 parties) → Dossier OPCO (créé/transmis)
 *     → Admission officielle (« À vérifier ») → éventuellement Rupture.
 *
 * Chaque statut déclencheur crée automatiquement l'enregistrement de la
 * section suivante, à son statut par défaut : matching accepté → contrat ;
 * contrat signé → dossier OPCO ; OPCO créé/transmis → admission
 * « À vérifier » ; admission en rupture → dossier rupture « À traiter ».
 *
 * EXCEPTION (choix métier) : l'acceptation d'un candidat n'ouvre PAS
 * automatiquement le Matching. L'équipe CFA l'y envoie explicitement via
 * {@see self::envoyerVersMatching()} / {@see self::proposerSurOffre()}.
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

    public const MSG_CV_OBLIGATOIRE = 'Un CV est obligatoire pour envoyer une proposition à une offre.';

    public const MSG_DEJA_PROPOSE = 'Ce candidat a déjà été proposé à cette offre.';

    public const MSG_DEJA_EN_RECHERCHE = 'Ce candidat est déjà en recherche entreprise.';

    public const MSG_MATCHING_NON_ACCEPTE = 'Impossible de créer un contrat : le matching n\'est pas accepté.';

    public const MSG_MATCHING_SANS_ENTREPRISE = 'Impossible de créer un contrat : aucune entreprise n\'est rattachée à ce matching.';

    public const MSG_CONTRAT_NON_SIGNE = 'Le dossier OPCO ne peut pas être créé tant que le contrat n\'est pas signé par les trois parties.';

    public const MSG_OPCO_MANQUANT = 'Impossible de créer une admission : le dossier OPCO n\'est pas encore accepté par l\'OPCO.';

    public const MSG_RETOUR_ENTRETIEN = 'Impossible de revenir au statut « Entretien prévu » après une décision finale.';

    public const MSG_RETOUR_A_PLANIFIER = 'Impossible de revenir au statut « Entretien à planifier » après une décision finale.';

    public const MSG_ENTRETIEN_INCOMPLET = 'Un entretien ne peut être planifié que si la date, l\'heure de début et l\'heure de fin sont renseignées.';

    public const MSG_ENTRETIEN_NON_PLANIFIE = 'Impossible de passer à « Entretien prévu » : aucun entretien planifié (date et heures) pour ce candidat.';

    public const MSG_ACCEPTATION_SANS_ENTRETIEN = 'Impossible d\'accepter ce candidat : aucun entretien réalisé. (Un administrateur peut passer outre.)';

    public const MSG_ENTRETIEN_EN_COURS = 'Un entretien est déjà en cours pour ce candidat. Gérez-le depuis la section Entretiens (reprogrammer, réaliser, annuler).';

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
    public const CONTRATS_ACTIFS_EXCLUS = [ContractStatut::Rompu];

    /**
     * Le dossier OPCO est-il accepté (financement validé) ? Seule l'acceptation
     * de l'OPCO transfère le dossier vers l'admission officielle pour validation
     * finale — un dossier déposé, en attente ou rejeté n'y arrive jamais. Le
     * rejet renvoie le contrat en « À corriger » (section Contrats), pas ici.
     */
    public static function opcoOuvreAdmission(OpcoStatut $statut): bool
    {
        return in_array($statut, [OpcoStatut::Accepte, OpcoStatut::Cloture], true);
    }

    /* ----------------------------------------------------------------
     |  Entretiens → statut candidat
     * ---------------------------------------------------------------- */

    /**
     * Synchronise le statut du candidat avec ses entretiens (appelé à chaque
     * enregistrement d'un entretien) :
     *  - entretien planifié → candidat « Entretien prévu » ;
     *  - entretien annulé / absent / à reprogrammer → retour à « Entretien à
     *    planifier » s'il ne reste aucun autre entretien planifié.
     * Une décision finale (Accepté / Refusé) n'est jamais remise en cause.
     */
    public function synchroniserCandidatDepuisEntretien(Entretien $entretien): void
    {
        $candidate = $entretien->candidate;

        if ($candidate === null || $candidate->statut->estFinal()) {
            return;
        }

        if ($entretien->statut === EntretienStatut::Planifie) {
            if ($candidate->statut === CandidateStatut::EntretienAPlanifier) {
                $candidate->transitionTo(CandidateStatut::EntretienPrevu);
            }

            return;
        }

        // L'entretien a eu lieu : le candidat passe à « Entretien réalisé »
        // (l'équipe CFA doit maintenant décider : Accepté ou Refusé). Pas de
        // départ automatique vers le Matching à ce stade.
        if ($entretien->statut === EntretienStatut::Realise) {
            if ($candidate->statut === CandidateStatut::EntretienPrevu) {
                $candidate->transitionTo(CandidateStatut::EntretienRealise);
            }

            return;
        }

        $enSuspens = in_array($entretien->statut, [
            EntretienStatut::Annule,
            EntretienStatut::Absent,
            EntretienStatut::AReprogrammer,
        ], true);

        if ($enSuspens
            && $candidate->statut === CandidateStatut::EntretienPrevu
            && ! $candidate->entretiens()
                ->where('id', '!=', $entretien->id)
                ->where('statut', EntretienStatut::Planifie->value)
                ->exists()) {
            $candidate->transitionTo(CandidateStatut::EntretienAPlanifier);
        }
    }

    /**
     * Décision à l'issue d'un entretien réalisé : accepte ou refuse le
     * candidat. L'acceptation déclenche automatiquement l'ouverture de la
     * recherche d'entreprise (Matching « En recherche »).
     *
     * @throws CycleBloqueException entretien non réalisé ou décision déjà prise
     */
    public function deciderApresEntretien(Entretien $entretien, bool $accepte, ?string $compteRendu = null): Candidate
    {
        if ($entretien->statut !== EntretienStatut::Realise) {
            throw new CycleBloqueException('La décision ne peut être prise qu\'après un entretien marqué « Réalisé ».');
        }

        $candidate = $entretien->candidate;

        if ($candidate === null || $candidate->statut->estFinal()) {
            throw new CycleBloqueException('Décision finale déjà prise pour ce candidat.');
        }

        $entretien->forceFill(array_filter([
            'resultat' => $accepte ? 'accepte' : 'refuse',
            'compte_rendu' => $compteRendu,
        ], fn ($v) => filled($v)))->save();

        $candidate->transitionTo($accepte ? CandidateStatut::Accepte : CandidateStatut::Refuse);

        return $candidate->refresh();
    }

    /* ----------------------------------------------------------------
     |  Candidat accepté → Matching (ouverture d'une recherche)
     * ---------------------------------------------------------------- */

    /**
     * Ouvre une recherche d'entreprise au Matching pour un candidat accepté
     * (statut par défaut « En recherche », sans entreprise rattachée). Anti-
     * doublon : aucun nouveau dossier si un matching actif ou accepté existe
     * déjà — renvoie null dans ce cas.
     *
     * ⚠️ N'est PLUS déclenché automatiquement à l'acceptation (choix métier) :
     * l'entrée au Matching est une décision explicite de l'équipe CFA. Voir
     * {@see self::envoyerVersMatching()}, utilisé par l'action « Envoyer vers
     * Matching » de la liste Candidats.
     */
    public function ouvrirRechercheEntreprise(Candidate $candidate): ?Matching
    {
        if ($candidate->statut !== CandidateStatut::Accepte) {
            return null;
        }

        $dejaEnCours = $candidate->matchings()
            ->whereIn('statut', array_map(
                fn (MatchingStatut $s) => $s->value,
                [...self::MATCHING_ACTIFS, MatchingStatut::Accepte],
            ))
            ->exists();

        if ($dejaEnCours) {
            return null;
        }

        $matching = Matching::query()->create([
            'candidate_id' => $candidate->id,
            'need_id' => null,
            'origine' => self::ORIGINE_CFA,
            'statut' => MatchingStatut::EnRecherche->value,
            'assigned_by' => Auth::id(),
        ]);

        self::notifierAutomatisme(
            'Candidat accepté',
            "Dossier Matching créé automatiquement pour {$candidate->nom_complet} (« En recherche »).",
        );

        return $matching;
    }

    /* ----------------------------------------------------------------
     |  Candidat → Matching (rattachement d'un besoin entreprise)
     * ---------------------------------------------------------------- */

    /**
     * Envoie un candidat accepté vers le Matching.
     *
     *  - sans offre (`$need === null`) : garantit une recherche « En
     *    recherche » ouverte (réutilise l'existante — anti-doublon — ou la
     *    crée). Le candidat cherche encore une entreprise ;
     *  - avec une offre : rattache le besoin à la recherche (statut « En
     *    recherche » conservé — l'entreprise est identifiée mais la
     *    proposition n'est pas encore envoyée).
     *
     * Pour envoyer une **proposition** à une offre (CV requis, statut
     * « Proposition envoyée »), utiliser {@see self::proposerSurOffre()}.
     *
     * @throws CycleBloqueException candidat non accepté ou matching déjà existant
     */
    public function envoyerVersMatching(Candidate $candidate, ?Need $need = null, string $origine = self::ORIGINE_CFA): Matching
    {
        if ($candidate->statut !== CandidateStatut::Accepte) {
            throw new CycleBloqueException(self::MSG_CANDIDAT_NON_ACCEPTE);
        }

        // Sans offre : une seule recherche active par candidat (idempotent).
        if ($need === null) {
            $existant = $candidate->matchings()
                ->whereIn('statut', array_map(
                    fn (MatchingStatut $s) => $s->value,
                    [...self::MATCHING_ACTIFS, MatchingStatut::Accepte],
                ))
                ->latest('id')
                ->first();

            if ($existant !== null) {
                return $existant;
            }

            return Matching::query()->create([
                'candidate_id' => $candidate->id,
                'need_id' => null,
                'origine' => self::ORIGINE_CFA,
                'statut' => MatchingStatut::EnRecherche->value,
                'assigned_by' => Auth::id(),
            ]);
        }

        if (Matching::query()->where('need_id', $need->id)->where('candidate_id', $candidate->id)->exists()) {
            throw new CycleBloqueException(self::MSG_MATCHING_EXISTANT);
        }

        // La recherche ouverte automatiquement (sans entreprise) est réutilisée.
        $recherche = $candidate->matchings()
            ->whereNull('need_id')
            ->whereIn('statut', array_map(fn (MatchingStatut $s) => $s->value, self::MATCHING_ACTIFS))
            ->first();

        if ($recherche !== null) {
            $recherche->forceFill([
                'need_id' => $need->id,
                'origine' => $origine,
                'assigned_by' => Auth::id() ?? $recherche->assigned_by,
            ])->save();

            return $recherche->refresh();
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
     * Envoie une **proposition** d'un candidat accepté sur une offre précise :
     * le CV est obligatoire (existant au profil ou ajouté au moment de
     * l'envoi), le matching passe à « Proposition envoyée » et `cv_envoye`
     * est marqué. Réutilise la recherche automatique ouverte à l'acceptation
     * plutôt que d'en créer une seconde (anti-doublon).
     *
     * @param  bool  $cvDisponible  le candidat dispose d'un CV (existant ou nouvellement ajouté)
     *
     * @throws CycleBloqueException candidat non accepté, CV manquant, ou candidat déjà proposé à cette offre
     */
    public function proposerSurOffre(Candidate $candidate, Need $need, bool $cvDisponible, string $origine = self::ORIGINE_CFA): Matching
    {
        if ($candidate->statut !== CandidateStatut::Accepte) {
            throw new CycleBloqueException(self::MSG_CANDIDAT_NON_ACCEPTE);
        }

        // Garde métier (backend, pas seulement l'interface) : pas de proposition sans CV.
        if (! $cvDisponible) {
            throw new CycleBloqueException(self::MSG_CV_OBLIGATOIRE);
        }

        if (Matching::query()->where('need_id', $need->id)->where('candidate_id', $candidate->id)->exists()) {
            throw new CycleBloqueException(self::MSG_DEJA_PROPOSE);
        }

        $recherche = $candidate->matchings()
            ->whereNull('need_id')
            ->whereIn('statut', array_map(fn (MatchingStatut $s) => $s->value, self::MATCHING_ACTIFS))
            ->first();

        if ($recherche !== null) {
            $recherche->forceFill([
                'need_id' => $need->id,
                'origine' => $origine,
                'cv_envoye' => true,
                'statut' => MatchingStatut::PropositionEnvoyee->value,
                'assigned_by' => Auth::id() ?? $recherche->assigned_by,
            ])->save();

            return $recherche->refresh();
        }

        return Matching::query()->create([
            'candidate_id' => $candidate->id,
            'need_id' => $need->id,
            'origine' => $origine,
            'cv_envoye' => true,
            'statut' => MatchingStatut::PropositionEnvoyee->value,
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

            // La recherche ouverte automatiquement (sans entreprise) est réutilisée.
            $recherche = $candidate->matchings()
                ->whereNull('need_id')
                ->whereIn('statut', array_map(fn (MatchingStatut $s) => $s->value, self::MATCHING_ACTIFS))
                ->first();

            if ($recherche !== null) {
                $recherche->forceFill([
                    'need_id' => $need->id,
                    'origine' => self::ORIGINE_CANDIDAT,
                ])->save();

                return $recherche->refresh();
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

        if ($need === null || $need->company_id === null) {
            throw new CycleBloqueException(self::MSG_MATCHING_SANS_ENTREPRISE);
        }

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
            'statut_contrat' => ContractStatut::EnCours->value,
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

        $admission = Admission::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'candidate_id' => $contract->candidate_id,
                'statut' => AdmissionStatut::AVerifier->value,
            ],
        );

        if ($admission->wasRecentlyCreated) {
            self::notifierAutomatisme(
                'Dossier OPCO accepté',
                'Admission ouverte automatiquement pour validation finale (« À vérifier ») : '
                .($contract->candidate?->nom_complet ?? 'l\'apprenant').'.',
            );
        }

        return $admission;
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

        $rupture = Rupture::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            array_filter($attributs, fn ($v) => filled($v)) + [
                'date_rupture' => now()->toDateString(),
                'motif' => RuptureMotif::Autre->value,
                'statut' => RuptureStatut::ATraiter->value,
                'created_by' => Auth::id(),
            ],
        );

        if ($rupture->wasRecentlyCreated) {
            self::notifierAutomatisme(
                'Admission en rupture',
                'Dossier Rupture créé automatiquement (« À traiter ») pour '
                .($contract->candidate?->nom_complet ?? 'l\'apprenant').'.',
            );
        }

        return $rupture;
    }

    /* ----------------------------------------------------------------
     |  Notifications des automatismes
     * ---------------------------------------------------------------- */

    /**
     * Notifie l'utilisateur qu'une étape a été créée automatiquement.
     * Silencieux hors contexte web (migrations, seeders, files d'attente) :
     * les automatismes ne doivent jamais échouer à cause de la notification.
     */
    public static function notifierAutomatisme(string $titre, string $corps): void
    {
        try {
            Notification::make()
                ->success()
                ->title($titre)
                ->body($corps)
                ->send();
        } catch (\Throwable) {
            // Pas de session (console, job) : l'automatisme reste silencieux.
        }
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
            ->where('statut_contrat', '!=', ContractStatut::Rompu->value)
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

        $entretienRealise = $candidate->entretiens()->where('statut', EntretienStatut::Realise->value)->exists();
        $entretienPlanifie = $candidate->entretiens()->where('statut', EntretienStatut::Planifie->value)->exists();
        $entretienEnSuspens = $candidate->entretiens()
            ->whereIn('statut', [EntretienStatut::Absent->value, EntretienStatut::AReprogrammer->value, EntretienStatut::APlanifier->value])
            ->exists();

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
            'cle' => 'entretien',
            'libelle' => 'Entretien',
            'etat' => match (true) {
                $entretienRealise || $candidate->statut === CandidateStatut::Accepte => self::ETAT_TERMINEE,
                $refuse => self::ETAT_BLOQUEE,
                $entretienPlanifie => self::ETAT_EN_COURS,
                $entretienEnSuspens => self::ETAT_EN_COURS,
                default => self::ETAT_NON_DEMARREE,
            },
            'detail' => match (true) {
                $entretienRealise => 'Entretien réalisé',
                $candidate->statut === CandidateStatut::Accepte => 'Décision prise',
                $refuse => 'Candidat refusé',
                $entretienPlanifie => 'Entretien planifié',
                $entretienEnSuspens => 'À planifier / reprogrammer',
                default => 'Non démarré',
            },
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
                // Contrat sans matching (créé directement via l'assistant) :
                // l'entreprise est bien engagée, mais pas trouvée via le module
                // Matching — on le dit honnêtement plutôt que « Entreprise trouvée ».
                $contrat !== null => 'Entreprise contractualisée',
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
