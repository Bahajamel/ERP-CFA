<?php

namespace App\Console\Commands;

use App\Support\SecureMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Déplace vers le disque privé les pièces sensibles déjà stockées sur un disque
 * web-exposé (héritage d'avant le durcissement sécurité). À exécuter une fois en
 * production après déploiement ; idempotente (les pièces déjà privées sont ignorées).
 */
class SecuriserPiecesSensibles extends Command
{
    protected $signature = 'documents:securiser-pieces {--dry-run : Lister sans rien déplacer}';

    protected $description = 'Déplace les pièces sensibles (NIR, identité…) vers le disque privé';

    public function handle(): int
    {
        $disquePrive = config('documents.disque_prive');
        $dryRun = (bool) $this->option('dry-run');

        $aDeplacer = Media::query()
            ->whereIn('collection_name', SecureMedia::COLLECTIONS_SENSIBLES)
            ->where('disk', '!=', $disquePrive)
            ->get();

        if ($aDeplacer->isEmpty()) {
            $this->info('Aucune pièce sensible à déplacer : tout est déjà sur le disque privé.');

            return self::SUCCESS;
        }

        $this->warn("{$aDeplacer->count()} pièce(s) sensible(s) à déplacer vers « {$disquePrive} ».");

        $deplacees = 0;
        $introuvables = 0;

        foreach ($aDeplacer as $media) {
            $source = Storage::disk($media->disk);
            $cible = Storage::disk($disquePrive);
            $chemin = $media->getPathRelativeToRoot();

            $this->line("• [{$media->collection_name}] {$media->file_name} ({$media->disk} → {$disquePrive})");

            if ($dryRun) {
                continue;
            }

            if (! $source->exists($chemin)) {
                $this->error("  ⚠ fichier introuvable sur « {$media->disk} », ligne média conservée.");
                $introuvables++;

                continue;
            }

            // Copie via flux (fonctionne local ↔ objet), puis bascule de la
            // référence en base, puis suppression de la source une fois sûre.
            $cible->writeStream($chemin, $source->readStream($chemin));

            $media->disk = $disquePrive;
            $media->conversions_disk = $disquePrive;
            $media->save();

            $source->delete($chemin);
            $deplacees++;
        }

        if ($dryRun) {
            $this->info('Mode --dry-run : rien n\'a été déplacé.');

            return self::SUCCESS;
        }

        $this->info("Terminé : {$deplacees} pièce(s) sécurisée(s)".($introuvables > 0 ? ", {$introuvables} introuvable(s)." : '.'));

        return self::SUCCESS;
    }
}
