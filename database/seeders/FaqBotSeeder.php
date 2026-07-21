<?php

namespace Database\Seeders;

use App\Models\FaqBot;
use App\Models\FaqEntry;
use App\Models\Organisation;
use App\Support\Assistant\BaseFaq;
use Illuminate\Database\Seeder;

/**
 * Installe les cinq assistants FAQ et leur contenu, pour chaque CFA.
 *
 * Le contenu n'est pas réinventé : il reprend les réponses déjà rédigées et
 * validées dans {@see BaseFaq}, réparties entre les assistants selon leur
 * catégorie. Rien n'est perdu, et l'ERP dispose d'une FAQ utile dès le premier
 * jour — l'administrateur peut ensuite tout modifier depuis l'interface.
 *
 * Rejouable sans risque (updateOrCreate) : relancer le seeder remet le contenu
 * d'origine en place sans créer de doublon.
 */
class FaqBotSeeder extends Seeder
{
    /** Identité de chaque assistant : nom, teinte, icône, accueil. */
    private const BOTS = [
        'commercial' => [
            'name' => 'Assistant Commercial',
            'description' => 'Candidats, entreprises, offres, matching et admissions',
            'icon' => 'heroicon-o-user-group',
            'color' => '#2563eb',
            'welcome_message' => 'Bonjour 👋 Je peux vous aider sur les candidats, les entreprises, les offres, le matching et les admissions. Posez votre question ou choisissez un sujet ci-dessous.',
            'sort' => 1,
        ],
        'contrats' => [
            'name' => 'Assistant Contrats & OPCO',
            'description' => 'Contrats, CERFA, signatures, dossiers OPCO et ruptures',
            'icon' => 'heroicon-o-document-check',
            'color' => '#7c3aed',
            'welcome_message' => 'Bonjour 👋 Je peux vous aider sur les contrats, le CERFA, les signatures, les dossiers OPCO et les ruptures.',
            'sort' => 2,
        ],
        'finance' => [
            'name' => 'Assistant Finance',
            'description' => 'Factures, paiements, montants bloqués et relances',
            'icon' => 'heroicon-o-banknotes',
            'color' => '#047857',
            'welcome_message' => 'Bonjour 👋 Je peux vous aider sur les factures, les paiements, les montants attendus ou bloqués et les relances.',
            'sort' => 3,
        ],
        'scolarite' => [
            'name' => 'Assistant Scolarité',
            'description' => 'Formations, classes, séances, notes, assiduité et emploi du temps',
            'icon' => 'heroicon-o-academic-cap',
            'color' => '#ea580c',
            'welcome_message' => "Bonjour 👋 Je peux vous aider sur les formations, les classes, les séances, les notes, l'assiduité et l'emploi du temps.",
            'sort' => 4,
        ],
        'pilotage' => [
            'name' => 'Assistant Pilotage',
            'description' => 'Tâches, alertes, documents, qualité et administration',
            'icon' => 'heroicon-o-bell-alert',
            'color' => '#dc2626',
            'welcome_message' => 'Bonjour 👋 Je peux vous aider sur les tâches, les alertes, les documents, la qualité et le paramétrage du logiciel.',
            'sort' => 5,
        ],
    ];

    /**
     * Catégorie de la FAQ existante → assistant qui la reprend.
     * Toute catégorie non listée revient à « pilotage » (le filet).
     */
    private const CATEGORIES = [
        'Candidats' => 'commercial',
        'Admission' => 'commercial',
        'Entreprises' => 'commercial',
        'Besoins' => 'commercial',
        'Matching' => 'commercial',
        'Contrats' => 'contrats',
        'OPCO' => 'contrats',
        'Finance' => 'finance',
        'Formations' => 'scolarite',
        'Scolarité' => 'scolarite',
        'Notes' => 'scolarite',
    ];

    /**
     * Réponses ajoutées pour les assistants que la FAQ historique couvrait peu
     * (Finance, Contrats & OPCO) : sans elles, ces assistants n'auraient pas de
     * quoi proposer 3 à 5 suggestions.
     *
     * Chaque réponse décrit un comportement RÉEL du logiciel (statuts issus des
     * énumérations, écrans existants) — une FAQ inexacte serait pire que vide.
     *
     * @var array<string, list<array{question: string, answer: string, keywords: list<string>, category: string}>>
     */
    private const SUPPLEMENT = [
        'finance' => [
            [
                'question' => 'Que signifie un « montant bloqué » ?',
                'answer' => "C'est un montant qui ne peut pas encore être facturé, faute d'un préalable : contrat incomplet, dossier OPCO non accepté, pièce manquante… Le tableau « Cash bloqué par raison » de la page Finance regroupe ces montants par cause, avec le nombre de dossiers concernés.",
                'keywords' => ['montant', 'bloque', 'blocage', 'cash', 'facturable', 'raison'],
                'category' => 'Finance',
            ],
            [
                'question' => 'Comment voir le détail des dossiers bloqués ?',
                'answer' => 'Dans « Cash bloqué par raison », chaque ligne indique la raison du blocage, le montant et le nombre de dossiers. Le lien « Voir tous les dossiers » ouvre la liste correspondante pour traiter les cas un par un.',
                'keywords' => ['detail', 'dossiers', 'bloques', 'liste', 'voir', 'blocage'],
                'category' => 'Finance',
            ],
            [
                'question' => 'Comment filtrer le suivi financier ?',
                'answer' => 'Les filtres en haut de la page Finance permettent de restreindre le suivi par période, formation, entreprise, statut financier et responsable.',
                'keywords' => ['filtrer', 'filtre', 'periode', 'formation', 'entreprise', 'statut', 'responsable'],
                'category' => 'Finance',
            ],
            [
                'question' => 'Quels sont les statuts d\'une facture ?',
                'answer' => 'Une facture passe par : Brouillon (préparée), Émise (envoyée, en attente de paiement), Payée, ou Annulée.',
                'keywords' => ['statut', 'facture', 'brouillon', 'emise', 'payee', 'annulee', 'paiement'],
                'category' => 'Finance',
            ],
            [
                'question' => 'Comment exporter le suivi financier ?',
                'answer' => 'Le bouton « Exporter » en haut de la page Finance génère un export du suivi, en tenant compte des filtres appliqués.',
                'keywords' => ['exporter', 'export', 'excel', 'csv', 'telecharger'],
                'category' => 'Finance',
            ],
        ],
        'contrats' => [
            [
                'question' => 'Que signifient les statuts d\'un contrat ?',
                'answer' => 'Un contrat suit : En cours (en préparation), Manque signature (en attente des signatures), Complet (tout est signé et fourni), À corriger (une information ou une pièce doit être reprise), Rompu (rupture enregistrée).',
                'keywords' => ['statut', 'contrat', 'cours', 'signature', 'complet', 'corriger', 'rompu'],
                'category' => 'Contrats',
            ],
            [
                'question' => 'Que signifie « Prêt au dépôt » pour un dossier OPCO ?',
                'answer' => "Le dossier a toutes ses pièces réunies et peut être déposé auprès de l'OPCO. Le cycle complet est : Non créé → À préparer → Prêt au dépôt → Déposé → En attente retour OPCO → Accepté ou Rejeté.",
                'keywords' => ['pret', 'depot', 'deposer', 'opco', 'dossier', 'etape', 'cycle'],
                'category' => 'OPCO',
            ],
            [
                'question' => 'Que faire si le dossier OPCO est rejeté ?',
                'answer' => 'Un dossier rejeté passe « En correction » : reprenez les pièces ou informations signalées, puis basculez-le en « Corrigé » pour le redéposer. Il pourra ensuite repartir vers « Déposé » puis « Accepté ».',
                'keywords' => ['rejete', 'rejet', 'refus', 'correction', 'corriger', 'redeposer', 'opco'],
                'category' => 'OPCO',
            ],
        ],
    ];

    public function run(): void
    {
        $organisations = Organisation::query()->pluck('id');

        // Installation mono-CFA (ou base sans organisation) : on sème tout de même.
        foreach ($organisations->isEmpty() ? [null] : $organisations as $organisationId) {
            $this->semerPourCfa($organisationId);
        }
    }

    private function semerPourCfa(?int $organisationId): void
    {
        foreach (self::BOTS as $module => $identite) {
            $bot = FaqBot::withoutGlobalScopes()->updateOrCreate(
                ['organisation_id' => $organisationId, 'module' => $module],
                $identite + ['is_active' => true],
            );

            $this->semerEntrees($bot);
        }
    }

    /** Reprend les entrées de la FAQ existante qui relèvent de cet assistant. */
    private function semerEntrees(FaqBot $bot): void
    {
        $ordre = 0;

        foreach (BaseFaq::entrees() as $entree) {
            if (self::moduleDe($entree['categorie']) !== $bot->module) {
                continue;
            }

            $lien = $entree['lien'] ?? null;

            FaqEntry::withoutGlobalScopes()->updateOrCreate(
                ['faq_bot_id' => $bot->id, 'question' => $entree['question']],
                [
                    'organisation_id' => $bot->organisation_id,
                    'answer' => $entree['reponse'],
                    'keywords' => $entree['mots'],
                    'category' => $entree['categorie'],
                    'link_route' => $lien['route'] ?? null,
                    'link_label' => $lien['label'] ?? null,
                    'link_permission' => $lien['permission'] ?? null,
                    'is_active' => true,
                    'sort_order' => $ordre++,
                ],
            );
        }

        // Compléments propres à cet assistant (Finance, Contrats & OPCO).
        foreach (self::SUPPLEMENT[$bot->module] ?? [] as $entree) {
            FaqEntry::withoutGlobalScopes()->updateOrCreate(
                ['faq_bot_id' => $bot->id, 'question' => $entree['question']],
                [
                    'organisation_id' => $bot->organisation_id,
                    'answer' => $entree['answer'],
                    'keywords' => $entree['keywords'],
                    'category' => $entree['category'],
                    'is_active' => true,
                    'sort_order' => $ordre++,
                ],
            );
        }
    }

    private static function moduleDe(string $categorie): string
    {
        return self::CATEGORIES[$categorie] ?? 'pilotage';
    }
}
