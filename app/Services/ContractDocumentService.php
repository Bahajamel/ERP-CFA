<?php

namespace App\Services;

use App\Cerfa\CerfaApprentissage;
use App\Documents\ConventionFormation;
use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organisation;
use Filament\Facades\Filament;

/**
 * « Tour de contrôle » documentaire du contrat d'apprentissage.
 *
 * Centralise la génération, la sauvegarde (GED) et le suivi de complétude des
 * deux documents indissociables d'un dossier d'apprentissage :
 *   - le CERFA 10103*14 (contrat) — {@see CerfaApprentissage} ;
 *   - la convention de formation (Annexe n°2) — {@see ConventionFormation}.
 *
 * Le service détecte les informations manquantes AVANT génération (aucun
 * document incomplet sans alerte), calcule un score de complétude, et garantit
 * la cohérence : les deux documents partagent les mêmes sources de données.
 */
class ContractDocumentService
{
    public function __construct(
        private readonly CerfaApprentissage $cerfa,
        private readonly ConventionFormation $convention,
    ) {}

    /** États documentaires possibles d'un document du contrat. */
    public const ETAT_A_GENERER = 'a_generer';

    public const ETAT_GENERE = 'genere';

    public const ETAT_A_REGENERER = 'a_regenerer';

    /* ----------------------------------------------------------------
     |  Détection des informations manquantes
     * ---------------------------------------------------------------- */

    /**
     * Informations obligatoires manquantes pour un CERFA exploitable.
     *
     * @return list<string>
     */
    public function champsManquantsCerfa(Contract $contract): array
    {
        $cand = $contract->candidate;
        $co = $contract->company;
        $formation = $contract->formation ?? $cand?->formationVisee;

        $manquants = [];

        $this->exiger($manquants, blank($cand?->nom) || blank($cand?->prenom), 'Identité de l\'apprenti (nom et prénom)');
        $this->exiger($manquants, blank($cand?->date_naissance), 'Date de naissance de l\'apprenti');
        $this->exiger($manquants, blank($cand?->adresse) && blank($cand?->ville), 'Adresse de l\'apprenti');
        $this->exiger($manquants, blank($co?->raison_sociale), 'Raison sociale de l\'entreprise');
        $this->exiger($manquants, blank($co?->siret), 'SIRET de l\'entreprise');
        $this->exiger($manquants, blank($co?->adresse) && blank($co?->ville), 'Adresse de l\'entreprise');
        $this->exiger($manquants, $contract->tuteur === null, 'Maître d\'apprentissage (tuteur)');
        $this->exiger($manquants, blank($formation?->libelle), 'Intitulé de la formation');
        $this->exiger($manquants, blank($contract->code_rncp) && blank($formation?->code_rncp), 'Code RNCP');
        $this->exiger($manquants, blank($contract->dateDebutEffective()), 'Date de début du contrat');
        $this->exiger($manquants, blank($contract->dateFinEffective()), 'Date de fin du contrat');
        $this->exiger($manquants, blank($contract->salaire_mensuel_brut), 'Salaire mensuel brut');

        return $manquants;
    }

    /**
     * Informations obligatoires manquantes pour une convention exploitable.
     * Reprend le socle commun avec le CERFA (cohérence) puis ajoute les champs
     * propres à la convention : lieu de formation, identité du CFA, OPCO.
     *
     * @return list<string>
     */
    public function champsManquantsConvention(Contract $contract): array
    {
        $cand = $contract->candidate;
        $co = $contract->company;
        $formation = $contract->formation ?? $cand?->formationVisee;

        $manquants = [];

        $this->exiger($manquants, blank($cand?->nom) || blank($cand?->prenom), 'Identité de l\'apprenti (nom et prénom)');
        $this->exiger($manquants, blank($co?->raison_sociale), 'Raison sociale de l\'entreprise');
        $this->exiger($manquants, blank($co?->siret), 'SIRET de l\'entreprise');
        $this->exiger($manquants, blank($formation?->libelle), 'Intitulé de la formation');
        $this->exiger($manquants, blank($contract->code_rncp) && blank($formation?->code_rncp), 'Code RNCP');
        $this->exiger($manquants, blank($contract->dateDebutEffective()), 'Date de début du contrat');
        $this->exiger($manquants, blank($contract->dateFinEffective()), 'Date de fin du contrat');
        $this->exiger($manquants, blank($contract->lieuFormationLisible()), 'Lieu principal de formation');
        $this->exiger($manquants, $contract->opcoFile?->opco === null && $co?->opco === null, 'OPCO (opérateur de compétences)');

        // Identité du CFA : renseignée une seule fois dans Paramètres CFA.
        return array_merge($manquants, $this->champsManquantsCfa());
    }

    /**
     * Informations d'identité du CFA manquantes. Elles ne se saisissent PAS sur
     * chaque contrat mais une seule fois dans « Paramètres CFA » — d'où la
     * mention explicite, pour ne pas chercher un champ inexistant sur le contrat.
     *
     * @return list<string>
     */
    public function champsManquantsCfa(): array
    {
        $cfa = Organisation::courante();

        $manquants = [];

        $this->exiger($manquants, blank($cfa->raison_sociale) && blank($cfa->nom), 'Raison sociale du CFA');
        $this->exiger($manquants, blank($cfa->siret), 'SIRET du CFA');
        $this->exiger($manquants, blank($cfa->representant_nom), 'Représentant légal du CFA');

        return $manquants;
    }

    private function exiger(array &$manquants, bool $absent, string $libelle): void
    {
        if ($absent) {
            $manquants[] = $libelle;
        }
    }

    /* ----------------------------------------------------------------
     |  Complétude & état documentaire (tour de contrôle)
     * ---------------------------------------------------------------- */

    /**
     * Synthèse documentaire complète du contrat : pour chaque document
     * (CERFA, convention) l'état, la date de génération et les champs
     * manquants ; plus un score global de complétude (0-100) et un message.
     *
     * @return array{
     *   score:int,
     *   cerfa:array{etat:string,manquants:list<string>,document:?Document,genere_le:?string},
     *   convention:array{etat:string,manquants:list<string>,document:?Document,genere_le:?string},
     *   message:string
     * }
     */
    public function completude(Contract $contract): array
    {
        $manquantsCerfa = $this->champsManquantsCerfa($contract);
        $manquantsConv = $this->champsManquantsConvention($contract);
        $manquantsCfa = $this->champsManquantsCfa();

        // Sur les cartes des documents, on n'affiche que les champs du dossier
        // (l'identité du CFA a sa propre bannière + lien vers Paramètres CFA).
        $manquantsConvDossier = array_values(array_diff($manquantsConv, $manquantsCfa));

        $docCerfa = $this->dernierDocument($contract, DocumentType::Cerfa);
        $docConv = $this->dernierDocument($contract, DocumentType::Convention);

        $cerfa = [
            'etat' => $this->etatDocument($contract, $docCerfa),
            'manquants' => $manquantsCerfa,
            'document' => $docCerfa,
            'genere_le' => $docCerfa?->updated_at?->format('d/m/Y H:i'),
        ];

        $convention = [
            'etat' => $this->etatDocument($contract, $docConv),
            'manquants' => $manquantsConvDossier,
            'document' => $docConv,
            'genere_le' => $docConv?->updated_at?->format('d/m/Y H:i'),
        ];

        // Score : moitié « données complètes », moitié « documents générés à jour ».
        $totalChamps = 24; // 12 CERFA + 12 convention
        $remplis = $totalChamps - count($manquantsCerfa) - count($manquantsConv);
        $scoreDonnees = (int) round(max(0, $remplis) / $totalChamps * 60);

        $scoreDocs = 0;
        $scoreDocs += $cerfa['etat'] === self::ETAT_GENERE ? 20 : ($cerfa['etat'] === self::ETAT_A_REGENERER ? 10 : 0);
        $scoreDocs += $convention['etat'] === self::ETAT_GENERE ? 20 : ($convention['etat'] === self::ETAT_A_REGENERER ? 10 : 0);

        return [
            'score' => min(100, $scoreDonnees + $scoreDocs),
            'cerfa' => $cerfa,
            'convention' => $convention,
            'cfa' => [
                'manquants' => $manquantsCfa,
                'url' => Filament::getTenantProfileUrl(),
            ],
            'message' => $this->message($cerfa, $convention),
        ];
    }

    /** Message métier intelligent selon l'état des deux documents. */
    private function message(array $cerfa, array $convention): string
    {
        $cerfaExiste = $cerfa['etat'] !== self::ETAT_A_GENERER;
        $convExiste = $convention['etat'] !== self::ETAT_A_GENERER;
        $cerfaOk = $cerfa['etat'] === self::ETAT_GENERE;
        $convOk = $convention['etat'] === self::ETAT_GENERE;
        $aRegenerer = $cerfa['etat'] === self::ETAT_A_REGENERER
            || $convention['etat'] === self::ETAT_A_REGENERER;

        // Tout est généré et à jour.
        if ($cerfaOk && $convOk) {
            return 'Les deux documents sont générés et à jour.';
        }

        // Des documents existent mais le dossier a changé depuis leur génération :
        // ils sont potentiellement obsolètes (à régénérer) — surtout PAS « aucun ».
        if ($aRegenerer) {
            return 'Le dossier a été modifié depuis la dernière génération : pensez à régénérer les documents concernés.';
        }

        // La convention est bloquée par des informations manquantes.
        if (filled($convention['manquants'])) {
            return 'La convention ne peut pas être complète : '
                .count($convention['manquants']).' information(s) manquante(s).';
        }

        // Un seul des deux documents est réellement produit.
        if ($cerfaExiste && ! $convExiste) {
            return 'Le CERFA est généré, mais la convention reste à produire.';
        }

        if ($convExiste && ! $cerfaExiste) {
            return 'La convention est générée, mais le CERFA reste à produire.';
        }

        return 'Aucun document généré pour l\'instant : CERFA et convention restent à produire.';
    }

    /** État d'un document : à générer / généré / à régénérer (contrat modifié depuis). */
    private function etatDocument(Contract $contract, ?Document $document): string
    {
        if ($document === null || $document->getFirstMedia('fichier') === null) {
            return self::ETAT_A_GENERER;
        }

        // Contrat modifié après la dernière génération → document potentiellement obsolète.
        if ($contract->updated_at !== null && $document->updated_at !== null
            && $contract->updated_at->greaterThan($document->updated_at)) {
            return self::ETAT_A_REGENERER;
        }

        return self::ETAT_GENERE;
    }

    /* ----------------------------------------------------------------
     |  Génération & sauvegarde (GED)
     * ---------------------------------------------------------------- */

    /** Génère (ou régénère) le CERFA et l'enregistre dans la GED du contrat. */
    public function genererCerfa(Contract $contract): Document
    {
        $pdf = $this->cerfa->pour($contract);
        $nom = 'CERFA_'.$this->slug($contract).'.pdf';

        return $this->enregistrer($contract, DocumentType::Cerfa, $pdf, $nom);
    }

    /** Génère (ou régénère) la convention et l'enregistre dans la GED du contrat. */
    public function genererConvention(Contract $contract): Document
    {
        $pdf = $this->convention->pour($contract);
        $nom = 'Convention_'.$this->slug($contract).'.pdf';

        return $this->enregistrer($contract, DocumentType::Convention, $pdf, $nom);
    }

    /**
     * Enregistre le PDF généré comme document GED du contrat (un seul document
     * courant par type : régénérer remplace le fichier et la date). Le fichier
     * est stocké proprement via la médiathèque.
     */
    private function enregistrer(Contract $contract, DocumentType $type, string $pdf, string $nom): Document
    {
        $document = $this->dernierDocument($contract, $type) ?? $contract->documents()->make([
            'type' => $type->value,
            'source' => DocumentSource::Genere->value,
        ]);

        $document->forceFill([
            'type' => $type->value,
            'statut' => DocumentStatut::Recu->value,
            'source' => DocumentSource::Genere->value,
            'nom_fichier' => $type->getLabel(),
            'uploaded_by' => auth()->id(),
        ]);
        $document->documentable()->associate($contract);
        $document->save();

        // Remplace le fichier précédent (régénération) puis attache le nouveau.
        $document->clearMediaCollection('fichier');
        $document->addMediaFromString($pdf)->usingFileName($nom)->toMediaCollection('fichier');

        // Touche la date de génération pour l'état « à jour » (après clearMedia).
        $document->touch();

        return $document->fresh();
    }

    /** Dernier document d'un type donné rattaché au contrat. */
    private function dernierDocument(Contract $contract, DocumentType $type): ?Document
    {
        return $contract->documents()->where('type', $type->value)->latest('id')->first();
    }

    private function slug(Contract $contract): string
    {
        return (string) str($contract->candidate?->nom_complet ?? 'contrat_'.$contract->id)->slug();
    }
}
