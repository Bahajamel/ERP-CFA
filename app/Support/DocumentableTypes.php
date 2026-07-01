<?php

namespace App\Support;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\OpcoFile;

/**
 * Cibles polymorphes auxquelles un document (ou une note) peut être rattaché.
 * Centralisé ici pour être réutilisé par le formulaire GED et les vues 360°.
 */
class DocumentableTypes
{
    /** Classe du modèle => libellé français. */
    public const MAP = [
        Candidate::class => 'Candidat',
        Company::class => 'Entreprise',
        Contract::class => 'Contrat',
        OpcoFile::class => 'Dossier OPCO',
    ];

    /** Options pour un Select de type (classe => libellé). */
    public static function options(): array
    {
        return self::MAP;
    }

    /** Options d'enregistrements (id => libellé lisible) pour un type donné. */
    public static function records(?string $type): array
    {
        return match ($type) {
            Candidate::class => Candidate::query()
                ->get()
                ->mapWithKeys(fn (Candidate $c) => [$c->id => $c->nom_complet])
                ->all(),
            Company::class => Company::query()
                ->pluck('raison_sociale', 'id')
                ->all(),
            Contract::class => Contract::query()
                ->with('candidate')
                ->get()
                ->mapWithKeys(fn (Contract $c) => [$c->id => 'Contrat #'.$c->id.' — '.($c->candidate?->nom_complet ?? '')])
                ->all(),
            OpcoFile::class => OpcoFile::query()
                ->get()
                ->mapWithKeys(fn (OpcoFile $o) => [$o->id => 'Dossier OPCO #'.$o->id])
                ->all(),
            default => [],
        };
    }

    /** Libellé lisible d'une cible (type + enregistrement). */
    public static function label(?string $type, int|string|null $id): string
    {
        if ($type === null || $id === null) {
            return '—';
        }

        $typeLabel = self::MAP[$type] ?? class_basename($type);
        $records = self::records($type);

        return $typeLabel.' : '.($records[$id] ?? '#'.$id);
    }
}
