<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use Illuminate\Console\Command;

/**
 * Suspend les CFA dont l'essai gratuit est arrivé à échéance : bascule `actif`
 * à false quand `date_fin_essai` est dépassée.
 *
 * Le blocage d'accès découle de `actif` (cf. User::canAccessTenant) : les
 * membres du CFA ne peuvent plus se connecter, mais toutes les données sont
 * conservées et l'accès est réactivable d'un clic depuis le panneau éditeur.
 * Idempotent, exécuté quotidiennement.
 */
class SuspendreEssaisExpires extends Command
{
    protected $signature = 'essai:suspendre-expires';

    protected $description = 'Suspend les CFA dont l\'essai gratuit est arrivé à échéance';

    public function handle(): int
    {
        $expires = Organisation::query()
            ->where('actif', true)
            ->whereNotNull('date_fin_essai')
            ->where('date_fin_essai', '<=', now())
            ->get();

        foreach ($expires as $organisation) {
            $organisation->update(['actif' => false]);
            $this->line("Essai expiré, CFA suspendu : {$organisation->nom} ({$organisation->slug})");
        }

        $this->info($expires->count().' CFA suspendu(s).');

        return self::SUCCESS;
    }
}
