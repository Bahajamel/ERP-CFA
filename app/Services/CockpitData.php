<?php

namespace App\Services;

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\EntretienStatut;
use App\Enums\MatchingStatut;
use App\Enums\OpcoStatut;
use App\Enums\TaskStatut;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Entretien;
use App\Models\Interaction;
use App\Models\Matching;
use App\Models\Need;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\Rupture;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Agrégats du « cockpit de supervision » (tableau de bord d'accueil).
 *
 * Centralise TOUTES les données affichées par le cockpit — séparées de la
 * présentation (le widget/vue ne fait que consommer ces tableaux). Toutes les
 * requêtes reposent sur les mêmes sources que les widgets historiques
 * (DirectionStatsOverview, ConversionFunnelChart…) pour rester cohérentes.
 *
 * Aucune donnée fictive : chaque chiffre provient de la base. Quand une série
 * n'a pas encore d'historique, elle renvoie des zéros (le cockpit reste lisible).
 */
class CockpitData
{
    /** Nombre de mois d'historique par défaut pour les mini-courbes et l'évolution. */
    private const MOIS = 6;

    /** Fenêtre d'historique effective (pilotée par le filtre de période). */
    private int $mois = self::MOIS;

    /** Règle la fenêtre d'historique (3, 6 ou 12 mois) — fluide. */
    public function periode(int $mois): static
    {
        $this->mois = max(1, min(12, $mois));

        return $this;
    }

    /* ================================================================
     |  Navigation par département (onglets du cockpit)
     * ================================================================ */

    /**
     * Onglets « départements » filtrés par les droits de l'utilisateur.
     *
     * @return list<array{label:string,icon:string,url:string,actif:bool}>
     */
    public function departements(): array
    {
        $u = auth()->user();

        $tabs = [
            ['label' => 'Vue globale', 'icon' => 'chart-bar', 'actif' => true, 'voir' => true,
                'url' => route('filament.admin.pages.dashboard')],
            ['label' => 'Commercial', 'icon' => 'user-group', 'actif' => false, 'voir' => (bool) $u?->can('access_candidates'),
                'url' => route('filament.admin.resources.candidates.index')],
            ['label' => 'Admissions', 'icon' => 'check-badge', 'actif' => false, 'voir' => (bool) $u?->can('access_admissions'),
                'url' => route('filament.admin.resources.admissions.index')],
            ['label' => 'Contrats', 'icon' => 'pencil', 'actif' => false, 'voir' => (bool) $u?->can('access_contracts'),
                'url' => route('filament.admin.resources.contracts.index')],
            ['label' => 'Finance', 'icon' => 'banknotes', 'actif' => false, 'voir' => (bool) $u?->can('access_finance'),
                'url' => route('filament.admin.pages.finance')],
            ['label' => 'Pilotage', 'icon' => 'folder-open', 'actif' => false, 'voir' => (bool) $u?->can('access_opco'),
                'url' => route('filament.admin.resources.opco-files.index')],
        ];

        return array_values(array_map(
            fn (array $t): array => ['label' => $t['label'], 'icon' => $t['icon'], 'url' => $t['url'], 'actif' => $t['actif']],
            array_filter($tabs, fn (array $t): bool => $t['voir']),
        ));
    }

    /* ================================================================
     |  Bandeau « Supervision intelligente » (résumé du jour)
     * ================================================================ */

    /**
     * @return array{
     *   a_retenir:string,
     *   anomalies:list<string>,
     *   recommandations:list<string>,
     *   actions:list<string>
     * }
     */
    public function insights(): array
    {
        $candidatsCeMois = Candidate::whereBetween('created_at', [now()->startOfMonth(), now()])->count();
        $candidatsMoisDernier = Candidate::whereBetween('created_at', [
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        ])->count();
        $evolution = $this->pourcentage($candidatsMoisDernier, $candidatsCeMois);

        $aRetenir = $evolution > 0
            ? "Bonne dynamique commerciale ce mois-ci : +{$evolution}\u{00A0}% de nouveaux candidats vs le mois dernier."
            : ($evolution < 0
                ? "Vigilance : {$evolution}\u{00A0}% de nouveaux candidats vs le mois dernier — relancer la prospection."
                : 'Activité stable ce mois-ci. Concentrez-vous sur les dossiers à débloquer.');

        // Anomalies
        $anomalies = [];
        $opcoBloques = OpcoFile::whereIn('statut', OpcoStatut::bloques())->count();
        $opcoVieux = OpcoFile::whereIn('statut', OpcoStatut::bloques())
            ->where('updated_at', '<', now()->subDays(7))->count();
        if ($opcoVieux > 0) {
            $anomalies[] = "{$opcoVieux} dossier(s) OPCO bloqué(s) depuis plus de 7 jours.";
        } elseif ($opcoBloques > 0) {
            $anomalies[] = "{$opcoBloques} dossier(s) OPCO à débloquer (rejet ou correction).";
        }
        $facturesRetard = OpcoPayment::enRetard()->count();
        if ($facturesRetard > 0) {
            $anomalies[] = "{$facturesRetard} versement(s) OPCO en retard à recouvrer.";
        }
        if ($evolution < 0) {
            $anomalies[] = 'Recrutement de candidats en baisse ce mois-ci.';
        }
        if (empty($anomalies)) {
            $anomalies[] = 'Aucune anomalie majeure détectée aujourd\'hui.';
        }

        // Recommandations
        $recommandations = [];
        $sansRetour = Interaction::query()->relanceDue()->count();
        if ($sansRetour > 0) {
            $recommandations[] = "Relancer {$sansRetour} interlocuteur(s) sans retour.";
        }
        $aPlanifier = Candidate::where('statut', CandidateStatut::EntretienAPlanifier->value)->count();
        if ($aPlanifier > 0) {
            $recommandations[] = "Planifier un entretien pour {$aPlanifier} candidat(s) en attente.";
        }
        $besoins = Need::query()->ouverts()->count();
        if ($besoins > 0) {
            $recommandations[] = "Proposer des profils sur {$besoins} besoin(s) ouvert(s).";
        }
        if (empty($recommandations)) {
            $recommandations[] = 'Tout est à jour : aucune action recommandée dans l\'immédiat.';
        }

        // Actions prioritaires (dérivées des tâches prioritaires)
        $actions = collect($this->priorityTasks())->take(3)
            ->map(fn (array $t): string => $t['titre'])->values()->all();
        if (empty($actions)) {
            $actions[] = 'Aucune action urgente en attente.';
        }

        return [
            'a_retenir' => $aRetenir,
            'anomalies' => $anomalies,
            'recommandations' => $recommandations,
            'actions' => $actions,
        ];
    }

    /* ================================================================
     |  Cartes KPI (valeur + tendance + mini-courbe)
     * ================================================================ */

    /**
     * Cartes KPI = « dossiers qui demandent une intervention », un par
     * département du CFA. Chaque chiffre est un goulot d'étranglement concret
     * qui fait avancer le travail quand on le traite (même philosophie que les
     * blocs rapides candidats), et non un simple volume ou un indicateur de
     * vanité. Le clic renvoie vers la section concernée.
     *
     * @return list<array{
     *   cle:string, label:string, valeur:string, sous:string, tone:string,
     *   icon:string, trend:int, spark:list<int|float>, url:?string
     * }>
     */
    public function kpis(): array
    {
        // 1. Commercial : candidats en attente d'un créneau d'entretien.
        $entretiensAPlanifier = Candidate::where('statut', CandidateStatut::EntretienAPlanifier->value)->count();
        $sparkCandidats = $this->serieMensuelle(Candidate::query());

        // 2. Matching : offres ouvertes sans aucun candidat proposé.
        $offresSansCandidat = Need::query()->ouverts()->whereDoesntHave('matchings')->count();
        $sparkBesoins = $this->serieMensuelle(Need::query());

        // 3. Admissions : dossiers à vérifier avant validation finale.
        $admissionsAValider = Admission::where('statut', AdmissionStatut::AVerifier->value)->count();
        $sparkAdmissions = $this->serieMensuelle(Admission::query());

        // 4. Contrats : contrats non signés par les trois parties (à relancer).
        $contratsASigner = Contract::where('statut_signature', '!=', ContractSignatureStatut::Signe->value)->count();
        $sparkContrats = $this->serieMensuelle(Contract::query());

        // 5. Pilotage OPCO : dossiers bloqués (rejet ou correction).
        $opcoADebloquer = OpcoFile::whereIn('statut', OpcoStatut::bloques())->count();
        $sparkOpco = $this->serieMensuelle(OpcoFile::query());

        // 6. Finance : versements OPCO échus non encaissés (à recouvrer).
        $versementsRetard = OpcoPayment::enRetard()->count();
        $sparkFinance = $this->serieMensuelle(OpcoPayment::query());

        return [
            [
                'cle' => 'entretiens', 'label' => 'Entretiens à planifier', 'valeur' => (string) $entretiensAPlanifier,
                'sous' => 'Candidats sans créneau', 'tone' => 'info', 'icon' => 'calendar',
                'trend' => $this->trendSerie($sparkCandidats), 'spark' => $sparkCandidats,
                'url' => route('filament.admin.resources.candidates.index'),
            ],
            [
                'cle' => 'offres', 'label' => 'Offres sans candidat', 'valeur' => (string) $offresSansCandidat,
                'sous' => 'À proposer au matching', 'tone' => 'warning', 'icon' => 'briefcase',
                'trend' => $this->trendSerie($sparkBesoins), 'spark' => $sparkBesoins,
                'url' => route('filament.admin.resources.needs.index'),
            ],
            [
                'cle' => 'admissions', 'label' => 'Admissions à valider', 'valeur' => (string) $admissionsAValider,
                'sous' => 'Dossiers à vérifier', 'tone' => 'turquoise', 'icon' => 'check-badge',
                'trend' => $this->trendSerie($sparkAdmissions), 'spark' => $sparkAdmissions,
                'url' => route('filament.admin.resources.admissions.index'),
            ],
            [
                'cle' => 'contrats', 'label' => 'Contrats à faire signer', 'valeur' => (string) $contratsASigner,
                'sous' => 'Signature incomplète', 'tone' => 'primary', 'icon' => 'pencil',
                'trend' => $this->trendSerie($sparkContrats), 'spark' => $sparkContrats,
                'url' => route('filament.admin.resources.contracts.index'),
            ],
            [
                'cle' => 'opco', 'label' => 'Dossiers OPCO à débloquer', 'valeur' => (string) $opcoADebloquer,
                'sous' => 'Rejet ou correction', 'tone' => 'violet', 'icon' => 'folder-open',
                'trend' => $this->trendSerie($sparkOpco), 'spark' => $sparkOpco,
                'url' => route('filament.admin.resources.opco-files.index'),
            ],
            [
                'cle' => 'versements', 'label' => 'Versements en retard', 'valeur' => (string) $versementsRetard,
                'sous' => 'Échéances à recouvrer', 'tone' => 'danger', 'icon' => 'banknotes',
                'trend' => $this->trendSerie($sparkFinance), 'spark' => $sparkFinance,
                'url' => route('filament.admin.pages.finance'),
            ],
        ];
    }

    /* ================================================================
     |  Pipeline commercial (entonnoir)
     * ================================================================ */

    /**
     * @return array{etapes:list<array{label:string,valeur:int,taux:int,url:string}>,global:int}
     */
    public function pipeline(): array
    {
        $candidats = Candidate::whereNot('statut', CandidateStatut::Refuse->value)->count();
        $entretiens = Candidate::whereIn('statut', [
            CandidateStatut::EntretienPrevu->value,
            CandidateStatut::EntretienRealise->value,
            CandidateStatut::Accepte->value,
        ])->count();
        $propositions = Matching::whereIn('statut', [
            MatchingStatut::PropositionEnvoyee->value,
            MatchingStatut::EntretienEntreprise->value,
            MatchingStatut::Accepte->value,
        ])->count();
        $contrats = Contract::whereIn('statut_contrat', ContractStatut::signes())->count();

        $base = max($candidats, 1);
        $etapes = [
            ['label' => 'Candidats', 'valeur' => $candidats, 'url' => route('filament.admin.resources.candidates.index')],
            ['label' => 'Entretiens', 'valeur' => $entretiens, 'url' => route('filament.admin.resources.candidates.index')],
            ['label' => 'Propositions', 'valeur' => $propositions, 'url' => route('filament.admin.resources.matchings.index')],
            ['label' => 'Contrats', 'valeur' => $contrats, 'url' => route('filament.admin.resources.contracts.index')],
        ];
        foreach ($etapes as &$e) {
            $e['taux'] = (int) round($e['valeur'] / $base * 100);
        }

        return [
            'etapes' => $etapes,
            'global' => $candidats > 0 ? (int) round($contrats / $candidats * 100) : 0,
        ];
    }

    /* ================================================================
     |  Évolution mensuelle (multi-séries)
     * ================================================================ */

    /**
     * @return array{labels:list<string>,series:list<array{label:string,color:string,data:list<int>}>}
     */
    public function evolution(): array
    {
        $labels = $this->labelsMois();

        return [
            'labels' => $labels,
            'series' => [
                [
                    'label' => 'Candidats',
                    'color' => '#3b82f6',
                    'data' => $this->serieMensuelle(Candidate::query()),
                ],
                [
                    'label' => 'Contrats signés',
                    'color' => '#10b981',
                    'data' => $this->serieMensuelle(Contract::whereIn('statut_contrat', ContractStatut::signes())),
                ],
                [
                    'label' => 'Besoins ouverts',
                    'color' => '#f59e0b',
                    'data' => $this->serieMensuelle(Need::query()),
                ],
            ],
        ];
    }

    /* ================================================================
     |  Répartition des contrats (anneau)
     * ================================================================ */

    /**
     * @return array{total:int,segments:list<array{label:string,valeur:int,color:string}>}
     */
    public function contractDistribution(): array
    {
        $enCours = Contract::where('statut_contrat', ContractStatut::EnCours->value)->count();
        $signesMois = Contract::whereIn('statut_contrat', ContractStatut::signes())
            ->whereBetween('created_at', [now()->startOfMonth(), now()])->count();
        $attenteOpco = OpcoFile::whereIn('statut', [
            OpcoStatut::Depose->value,
            OpcoStatut::AttenteRetour->value,
        ])->count();
        $resiliations = Rupture::count();

        $segments = [
            ['label' => 'En cours', 'valeur' => $enCours, 'color' => '#6366f1'],
            ['label' => 'Signés ce mois', 'valeur' => $signesMois, 'color' => '#10b981'],
            ['label' => 'En attente OPCO', 'valeur' => $attenteOpco, 'color' => '#f59e0b'],
            ['label' => 'Résiliations', 'valeur' => $resiliations, 'color' => '#f43f5e'],
        ];

        return [
            'total' => array_sum(array_column($segments, 'valeur')),
            'segments' => $segments,
        ];
    }

    /* ================================================================
     |  Tâches prioritaires
     * ================================================================ */

    /**
     * @return list<array{titre:string,detail:string,niveau:?string,tone:string,action:string,icon:string,url:?string}>
     */
    public function priorityTasks(): array
    {
        $items = [];

        $sansRetour = Interaction::query()->relanceDue()->count();
        if ($sansRetour > 0) {
            $items[] = [
                'titre' => 'Relancer les interlocuteurs sans retour',
                'detail' => $sansRetour.' relance(s) datée(s) échue(s)',
                'niveau' => 'Urgent', 'tone' => 'danger', 'action' => 'Relancer', 'icon' => 'paper-airplane',
                'url' => route('filament.admin.resources.candidates.index'),
            ];
        }

        $opcoAttente = OpcoFile::whereIn('statut', OpcoStatut::bloques())->count();
        if ($opcoAttente > 0) {
            $items[] = [
                'titre' => 'Dossiers OPCO à débloquer',
                'detail' => $opcoAttente.' dossier(s) rejeté(s) ou en correction',
                'niveau' => 'Important', 'tone' => 'warning', 'action' => 'Ouvrir', 'icon' => 'folder-open',
                'url' => route('filament.admin.resources.opco-files.index'),
            ];
        }

        $aPlanifier = Candidate::where('statut', CandidateStatut::EntretienAPlanifier->value)->count();
        if ($aPlanifier > 0) {
            $items[] = [
                'titre' => 'Entretiens à planifier',
                'detail' => $aPlanifier.' candidat(s) sans créneau',
                'niveau' => null, 'tone' => 'info', 'action' => 'Planifier', 'icon' => 'calendar',
                'url' => route('filament.admin.resources.candidates.index'),
            ];
        }

        $aSigner = Contract::where('statut_signature', '!=', ContractSignatureStatut::Signe->value)->count();
        if ($aSigner > 0) {
            $items[] = [
                'titre' => 'Contrats à faire signer',
                'detail' => $aSigner.' contrat(s) en attente de signature',
                'niveau' => null, 'tone' => 'primary', 'action' => 'Traiter', 'icon' => 'pencil',
                'url' => route('filament.admin.resources.contracts.index'),
            ];
        }

        return $items;
    }

    /* ================================================================
     |  Alertes critiques
     * ================================================================ */

    /**
     * @return list<array{titre:string,detail:string,tone:string,age:?string}>
     */
    public function criticalAlerts(): array
    {
        $alertes = [];

        OpcoFile::whereIn('statut', OpcoStatut::bloques())
            ->orderBy('updated_at')
            ->limit(3)
            ->get()
            ->each(function (OpcoFile $f) use (&$alertes): void {
                $jours = (int) $f->updated_at?->diffInDays(now());
                $ref = $f->reference ?? ('OPCO-'.$f->id);
                $alertes[] = [
                    'titre' => 'Dossier OPCO bloqué',
                    'detail' => "Le dossier {$ref} attend une action depuis {$jours} jour(s).",
                    'tone' => 'danger',
                    'age' => $jours.'j',
                ];
            });

        $facturesRetard = OpcoPayment::enRetard()->count();
        if ($facturesRetard > 0) {
            $alertes[] = [
                'titre' => 'Versements OPCO en retard',
                'detail' => "{$facturesRetard} échéance(s) attendue(s) et non encaissée(s).",
                'tone' => 'warning',
                'age' => null,
            ];
        }

        return $alertes;
    }

    /* ================================================================
     |  Agenda du jour
     * ================================================================ */

    /**
     * @return list<array{heure:string,titre:string,type:string,tone:string,url:?string}>
     */
    public function agenda(): array
    {
        $items = collect();

        Entretien::with('candidate')
            ->whereDate('date_entretien', today())
            ->whereIn('statut', [EntretienStatut::Planifie->value, EntretienStatut::APlanifier->value])
            ->get()
            ->each(function (Entretien $e) use ($items): void {
                $items->push([
                    'heure' => $e->heure_debut ? Carbon::parse($e->heure_debut)->format('H:i') : '—',
                    'titre' => 'Entretien — '.($e->candidate?->nom_complet ?? 'candidat'),
                    'type' => 'Entretien', 'tone' => 'info',
                    'url' => route('filament.admin.resources.entretiens.index'),
                ]);
            });

        Task::with('assignee')
            ->whereDate('due_date', today())
            ->whereNotIn('statut', [TaskStatut::Terminee->value, TaskStatut::Annulee->value])
            ->get()
            ->each(function (Task $t) use ($items): void {
                $items->push([
                    'heure' => '—',
                    'titre' => $t->titre,
                    'type' => 'Tâche', 'tone' => 'warning',
                    'url' => route('filament.admin.resources.tasks.index'),
                ]);
            });

        return $items->sortBy('heure')->values()->all();
    }

    /* ================================================================
     |  Activité récente
     * ================================================================ */

    /**
     * @return list<array{titre:string,auteur:string,quand:string,tone:string}>
     */
    public function recentActivity(): array
    {
        return Activity::query()
            ->with('causer')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Activity $a): array => [
                'titre' => $this->libelleActivite($a),
                'auteur' => $a->causer?->name ?? 'Système',
                'quand' => $a->created_at?->diffForHumans() ?? '',
                'tone' => match ($a->event) {
                    'created' => 'success',
                    'deleted' => 'danger',
                    default => 'info',
                },
            ])
            ->all();
    }

    /* ================================================================
     |  Helpers
     * ================================================================ */

    /** Étiquettes des N derniers mois (« janv. », « févr. »…). */
    private function labelsMois(): array
    {
        $labels = [];
        for ($i = $this->mois - 1; $i >= 0; $i--) {
            $labels[] = now()->subMonthsNoOverflow($i)->locale('fr')->translatedFormat('M');
        }

        return $labels;
    }

    /**
     * Série mensuelle (comptage) sur les N derniers mois pour un modèle donné,
     * regroupé sur une colonne de date (created_at par défaut).
     *
     * @return list<int>
     */
    private function serieMensuelle(Builder $query, string $colonne = 'created_at'): array
    {
        $debut = now()->subMonthsNoOverflow($this->mois - 1)->startOfMonth();

        $dates = (clone $query)
            ->whereBetween($colonne, [$debut, now()->endOfMonth()])
            ->pluck($colonne)
            ->filter();

        return $this->bucketsDepuis($dates, fn (Carbon $d): int => 1);
    }

    /** @return array<string,int> clés « Y-m » des N derniers mois initialisées à 0. */
    private function clesMois(): array
    {
        $buckets = [];
        for ($i = $this->mois - 1; $i >= 0; $i--) {
            $buckets[now()->subMonthsNoOverflow($i)->format('Y-m')] = 0;
        }

        return $buckets;
    }

    /** Répartit une collection de dates dans les buckets mensuels. */
    private function bucketsDepuis(Collection $dates, callable $poids): array
    {
        $buckets = $this->clesMois();
        foreach ($dates as $date) {
            $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
            $cle = $carbon->format('Y-m');
            if (isset($buckets[$cle])) {
                $buckets[$cle] += $poids($carbon);
            }
        }

        return array_values($buckets);
    }

    /** Tendance % entre l'avant-dernier et le dernier point d'une série. */
    private function trendSerie(array $serie): int
    {
        $n = count($serie);
        if ($n < 2) {
            return 0;
        }

        return $this->pourcentage((float) $serie[$n - 2], (float) $serie[$n - 1]);
    }

    /** Variation en pourcentage entre deux valeurs (borné, entier). */
    private function pourcentage(float $avant, float $apres): int
    {
        if ($avant <= 0) {
            return $apres > 0 ? 100 : 0;
        }

        return (int) round(($apres - $avant) / $avant * 100);
    }

    /** Libellé lisible d'une entrée du journal d'activité. */
    private function libelleActivite(Activity $a): string
    {
        $sujet = class_basename($a->subject_type ?? 'Élément');
        $traduction = [
            'Contract' => 'Contrat', 'Candidate' => 'Candidat', 'OpcoFile' => 'Dossier OPCO',
            'Admission' => 'Admission', 'Matching' => 'Mise en relation', 'Entretien' => 'Entretien',
            'Invoice' => 'Facture', 'FinanceLine' => 'Ligne financière', 'Document' => 'Document',
        ];
        $nom = $traduction[$sujet] ?? $sujet;
        $verbe = match ($a->event) {
            'created' => 'créé', 'updated' => 'mis à jour', 'deleted' => 'supprimé',
            default => 'modifié',
        };

        return "{$nom} {$verbe}";
    }
}
