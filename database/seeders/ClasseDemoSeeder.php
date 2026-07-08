<?php

namespace Database\Seeders;

use App\Enums\CandidateStatut;
use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration « scolarité » : pour CHAQUE formation, une cohorte
 * par année composée d'apprenants, et un emploi du temps hebdomadaire garni
 * (plusieurs matières réparties du lundi au vendredi, matin et après-midi),
 * décliné sur trois semaines — passée (émargée + validée), courante et
 * prochaine (planifiées). Idempotent (nettoyage de la fenêtre + firstOrCreate).
 */
class ClasseDemoSeeder extends Seeder
{
    /** Nombre d'apprenants visé par classe. */
    private const EFFECTIF = 6;

    /** Matières par formation (le programme de la cohorte). */
    private const MATIERES = [
        'Concepteur Développeur d\'Applications' => [
            'Développement web', 'Développement back-end', 'Bases de données',
            'Cybersécurité', 'Gestion de projet', 'Anglais professionnel',
        ],
        'Administrateur Systèmes & Réseaux' => [
            'Administration réseaux', 'Systèmes Linux', 'Virtualisation & Cloud',
            'Cybersécurité', 'Supervision', 'Anglais professionnel',
        ],
        'Négociation et Digitalisation de la Relation Client' => [
            'Relation client', 'Techniques de vente', 'Négociation commerciale',
            'Marketing digital', 'Communication professionnelle', 'Anglais professionnel',
        ],
        'Responsable Marketing Digital' => [
            'Marketing digital', 'Référencement SEO / SEA', 'Community management',
            'Web analytics', 'Stratégie de contenu', 'Anglais professionnel',
        ],
        'Gestionnaire Comptable et Fiscal' => [
            'Comptabilité générale', 'Fiscalité', 'Gestion de la paie',
            'Analyse financière', 'Droit social', 'Anglais professionnel',
        ],
    ];

    /** Créneaux hebdomadaires : [jour (0 = lundi … 4 = vendredi), début, fin]. */
    private const CRENEAUX = [
        [0, '09:00', '12:30'],
        [0, '14:00', '17:30'],
        [1, '09:00', '12:30'],
        [1, '14:00', '17:30'],
        [2, '09:00', '12:30'],
        [3, '09:00', '12:30'],
        [3, '14:00', '17:30'],
        [4, '09:00', '12:30'],
    ];

    public function run(): void
    {
        $formateur = User::where('email', 'formateur@cfa-v2s.fr')->first();
        $commercial = User::query()->first();

        // Toutes les matières connues (pour nettoyer nos anciennes séances sans
        // toucher aux séances saisies à la main, dont le libellé diffère).
        $matieresConnues = collect(self::MATIERES)->flatten()->unique()->all();

        foreach (Formation::all() as $formation) {
            $matieres = self::MATIERES[$formation->libelle] ?? ['Cours magistral', 'Travaux pratiques', 'Anglais professionnel'];

            $libelles = ['1ère année'];
            if (($formation->duree_mois ?? 12) > 12) {
                $libelles[] = '2ème année';
            }

            foreach ($libelles as $rang => $libelle) {
                $classe = Promotion::firstOrCreate(
                    [
                        'formation_id' => $formation->id,
                        'libelle' => $libelle,
                        'annee_scolaire' => '2025-2026',
                    ],
                    [
                        'date_debut' => '2025-09-01',
                        'date_fin' => '2026-08-31',
                    ],
                );

                $this->composerClasse($classe, $formation, $commercial);

                // La 2ème année démarre le programme décalé → emploi du temps distinct.
                $this->planifierEmploiDuTemps($classe, $formateur, $matieres, $rang, $matieresConnues);
            }
        }
    }

    /**
     * Complète la cohorte jusqu'à l'effectif cible : d'abord les candidats
     * « contrat signé » de la formation sans classe, puis des apprentis générés.
     */
    private function composerClasse(Promotion $classe, Formation $formation, ?User $commercial): void
    {
        $manque = self::EFFECTIF - $classe->apprentis()->count();

        if ($manque > 0) {
            $ids = Candidate::where('formation_visee_id', $formation->id)
                ->where('statut', CandidateStatut::Accepte)
                ->doesntHave('promotions')
                ->limit($manque)
                ->pluck('id');

            $classe->apprentis()->syncWithoutDetaching($ids->all());
            $manque = self::EFFECTIF - $classe->apprentis()->count();
        }

        if ($manque > 0) {
            Candidate::factory()->count($manque)->create([
                'formation_visee_id' => $formation->id,
                'statut' => CandidateStatut::Accepte,
                'source' => 'Démo scolarité',
                'commercial_id' => $commercial?->id,
            ])->each(fn (Candidate $c) => $classe->apprentis()->attach($c->id));
        }
    }

    /**
     * Génère l'emploi du temps hebdomadaire de la cohorte sur trois semaines :
     * chaque créneau reçoit une matière (assignation décalée pour la 2ème année),
     * la semaine passée est émargée puis validée, les deux autres sont planifiées.
     *
     * @param  list<string>  $matieres
     * @param  list<string>  $matieresConnues
     */
    private function planifierEmploiDuTemps(Promotion $classe, ?User $formateur, array $matieres, int $decalage, array $matieresConnues): void
    {
        $lundi = now()->startOfWeek();
        $semaines = [
            'passee' => $lundi->copy()->subWeek(),
            'courante' => $lundi->copy(),
            'prochaine' => $lundi->copy()->addWeek(),
        ];

        // Nettoie nos séances de la fenêtre (anciennes exécutions / anciens
        // programmes), sans toucher aux séances saisies à la main.
        $classe->seances()
            ->whereIn('libelle', $matieresConnues)
            ->whereBetween('date', [
                $semaines['passee']->toDateString(),
                $semaines['prochaine']->copy()->addDays(5)->toDateString(),
            ])
            ->delete();

        foreach ($semaines as $quand => $debutSemaine) {
            foreach (self::CRENEAUX as $i => [$jour, $heureDebut, $heureFin]) {
                // Matière du créneau (décalée d'une année à l'autre).
                $matiere = $matieres[($i + $decalage) % count($matieres)];
                $date = $debutSemaine->copy()->addDays($jour)->toDateString();

                $seance = $classe->seances()->firstOrCreate(
                    ['date' => $date, 'heure_debut' => $heureDebut, 'libelle' => $matiere],
                    ['heure_fin' => $heureFin, 'formateur_id' => $formateur?->id],
                );

                if ($quand === 'passee') {
                    $this->emargerEtValider($seance);
                }
            }
        }
    }

    /** Émarge la séance (tous présents sauf un absent justifié) puis la valide. */
    private function emargerEtValider($seance): void
    {
        if ($seance->statut === SeanceStatut::Validee) {
            return;
        }

        $presences = $seance->presences()->orderBy('candidate_id')->get();
        $presences->each(fn ($p, $i) => $p->update([
            'statut' => $i === $presences->count() - 1
                ? PresenceStatut::AbsentJustifie
                : PresenceStatut::Present,
        ]));

        $seance->update(['statut' => SeanceStatut::Validee]);
    }
}
