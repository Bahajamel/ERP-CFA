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
 * Données de démonstration « scolarité » : pour CHAQUE formation, des classes
 * (1ère année, + 2ème année si formation longue) composées d'apprenants visant
 * cette formation, et deux séances par classe (une passée émargée + validée,
 * une à venir) pour rendre l'émargement démontrable. Idempotent (firstOrCreate).
 */
class ClasseDemoSeeder extends Seeder
{
    /** Nombre d'apprenants visé par classe. */
    private const EFFECTIF = 5;

    public function run(): void
    {
        $formateur = User::where('email', 'formateur@cfa-v2s.fr')->first();
        $commercial = User::query()->first();

        $matieres = [
            'Concepteur Développeur d\'Applications' => 'Développement web',
            'Administrateur Systèmes & Réseaux' => 'Administration réseaux',
            'Négociation et Digitalisation de la Relation Client' => 'Relation client',
            'Responsable Marketing Digital' => 'Marketing digital',
            'Gestionnaire Comptable et Fiscal' => 'Comptabilité',
        ];

        foreach (Formation::all() as $formation) {
            $libelles = ['1ère année'];
            if (($formation->duree_mois ?? 12) > 12) {
                $libelles[] = '2ème année';
            }

            $creneau = 0; // répartit les classes de la formation sur la semaine

            foreach ($libelles as $libelle) {
                // Une classe = une matière : chaque année a sa matière « cœur de
                // métier » + l'anglais pro, suivies par les MÊMES apprenants.
                foreach ([$matieres[$formation->libelle] ?? 'Tronc commun', 'Anglais professionnel'] as $matiere) {
                    $classe = Promotion::firstOrCreate(
                        [
                            'formation_id' => $formation->id,
                            'libelle' => $libelle,
                            'annee_scolaire' => '2025-2026',
                            'matiere' => $matiere,
                        ],
                        [
                            'date_debut' => '2025-09-01',
                            'date_fin' => '2026-08-31',
                        ],
                    );

                    $this->composerClasse($classe, $formation, $commercial);
                    $this->planifierSeances($classe, $formateur, $creneau++);
                }
            }
        }
    }

    /**
     * Complète la classe jusqu'à l'effectif cible : d'abord la cohorte du même
     * niveau (les apprenants des autres matières de cette année), puis les
     * candidats de la formation sans classe, enfin des apprentis générés.
     */
    private function composerClasse(Promotion $classe, Formation $formation, ?User $commercial): void
    {
        // 1) La cohorte : mêmes apprenants que les autres matières de ce niveau.
        $manque = self::EFFECTIF - $classe->apprentis()->count();

        if ($manque > 0) {
            $ids = Candidate::where('formation_visee_id', $formation->id)
                ->whereHas('promotions', fn ($q) => $q
                    ->where('formation_id', $formation->id)
                    ->where('libelle', $classe->libelle))
                ->whereDoesntHave('promotions', fn ($q) => $q->whereKey($classe->id))
                ->limit($manque)
                ->pluck('id');

            $classe->apprentis()->syncWithoutDetaching($ids->all());
            $manque = self::EFFECTIF - $classe->apprentis()->count();
        }

        // 2) Les candidats « contrat signé » de la formation encore sans classe.
        if ($manque > 0) {
            $ids = Candidate::where('formation_visee_id', $formation->id)
                ->where('statut', CandidateStatut::ContratSigne)
                ->doesntHave('promotions')
                ->limit($manque)
                ->pluck('id');

            $classe->apprentis()->syncWithoutDetaching($ids->all());
            $manque = self::EFFECTIF - $classe->apprentis()->count();
        }

        // 3) Complète avec de nouveaux apprentis générés (formation cohérente).
        if ($manque > 0) {
            Candidate::factory()->count($manque)->create([
                'formation_visee_id' => $formation->id,
                'statut' => CandidateStatut::ContratSigne,
                'source' => 'Démo scolarité',
                'commercial_id' => $commercial?->id,
            ])->each(fn (Candidate $c) => $classe->apprentis()->attach($c->id));
        }
    }

    /**
     * Répartit la classe sur l'emploi du temps : un jour de semaine et un
     * créneau (matin/après-midi) stables, déclinés sur trois semaines —
     * passée (émargée puis validée), courante et prochaine (planifiées).
     */
    private function planifierSeances(Promotion $classe, ?User $formateur, int $creneau): void
    {
        $jour = $creneau % 5; // lundi → vendredi
        [$debut, $fin] = $creneau % 2 === 0 ? ['09:00', '12:30'] : ['14:00', '17:30'];

        $lundi = now()->startOfWeek();
        $dates = [
            'passee' => $lundi->copy()->subWeek()->addDays($jour)->toDateString(),
            'courante' => $lundi->copy()->addDays($jour)->toDateString(),
            'prochaine' => $lundi->copy()->addWeek()->addDays($jour)->toDateString(),
        ];

        // Nettoie les séances d'anciennes exécutions du seeder (toutes empilées
        // sur les mêmes dates) sans toucher aux séances créées à la main.
        $classe->seances()
            ->whereIn('libelle', array_filter([$classe->matiere, 'Atelier pratique', 'Gestion de projet']))
            ->whereNotIn('date', array_values($dates))
            ->delete();

        $passee = $classe->seances()->firstOrCreate(
            ['date' => $dates['passee'], 'libelle' => $classe->matiere ?? 'Atelier pratique'],
            ['heure_debut' => $debut, 'heure_fin' => $fin, 'formateur_id' => $formateur?->id],
        );

        if ($passee->statut !== SeanceStatut::Validee) {
            // Émarge : tout le monde présent sauf le dernier (absent justifié).
            $presences = $passee->presences()->orderBy('candidate_id')->get();
            $presences->each(fn ($p, $i) => $p->update([
                'statut' => $i === $presences->count() - 1
                    ? PresenceStatut::AbsentJustifie
                    : PresenceStatut::Present,
            ]));

            $passee->update(['statut' => SeanceStatut::Validee]);
        }

        foreach ([$dates['courante'], $dates['prochaine']] as $date) {
            $classe->seances()->firstOrCreate(
                ['date' => $date, 'libelle' => $classe->matiere ?? 'Gestion de projet'],
                ['heure_debut' => $debut, 'heure_fin' => $fin, 'formateur_id' => $formateur?->id],
            );
        }
    }
}
