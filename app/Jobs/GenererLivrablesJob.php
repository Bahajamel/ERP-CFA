<?php

namespace App\Jobs;

use App\Livret\LivrablePackImporter;
use App\Livret\LivrablePayloadBuilder;
use App\Livret\LivretRsClient;
use App\Livret\LivretRsException;
use App\Models\Contract;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Génère les livrables d'un apprenti via LivretRS puis les importe dans la GED,
 * en tâche de fond (la génération de plusieurs PDF prend ~30 s). L'utilisateur
 * qui a déclenché l'action est prévenu par une notification à la fin.
 */
class GenererLivrablesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /** @param array{theme_code?: string, format?: string, verifier_rncp?: bool} $options */
    public function __construct(
        public int $contractId,
        public ?int $userId = null,
        public array $options = [],
    ) {}

    public function handle(
        LivrablePayloadBuilder $builder,
        LivretRsClient $client,
        LivrablePackImporter $importer,
    ): void {
        $contract = Contract::with(['candidate', 'company', 'formation', 'tuteur'])->find($this->contractId);

        if ($contract === null || $contract->candidate === null) {
            $this->notifier('danger', 'Génération impossible', "Contrat ou apprenti introuvable.");

            return;
        }

        $apprenti = $contract->candidate->nom_complet;

        try {
            $payload = $builder->pour($contract, $this->options);
            $zip = $client->genererLivrables($payload);
        } catch (LivretRsException|RuntimeException $e) {
            $this->notifier('danger', "Génération échouée — {$apprenti}", $e->getMessage());

            return;
        }

        try {
            $result = $importer->import($contract, $zip, $this->userId);
        } finally {
            @unlink($zip);
        }

        $details = "{$result->importes} livrable(s) importé(s), {$result->missionsRattachees} mission(s) rattachée(s).";
        if ($result->nonReconnus() > 0) {
            $details .= " {$result->nonReconnus()} à taguer manuellement.";
        }

        $this->notifier('success', "Livrables générés — {$apprenti}", $details);
    }

    public function failed(Throwable $exception): void
    {
        $this->notifier('danger', 'Génération des livrables échouée', $exception->getMessage());
    }

    /** Notifie (cloche Filament) l'utilisateur déclencheur, s'il est connu. */
    private function notifier(string $couleur, string $titre, string $corps): void
    {
        $user = $this->userId !== null ? User::find($this->userId) : null;

        if ($user === null) {
            return;
        }

        $notification = Notification::make()->title($titre)->body($corps);
        $couleur === 'success' ? $notification->success() : $notification->danger();

        $notification->sendToDatabase($user);
    }
}
