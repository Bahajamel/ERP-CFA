<?php

namespace Tests;

use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les tests n'ont pas besoin des assets compilés : on neutralise @vite
        // pour que le rendu des pages ne dépende pas d'un manifeste build. Sans
        // cela, la CI (qui ne lance pas « npm run build ») échoue sur les pages
        // qui chargent le thème Filament.
        $this->withoutVite();

        // Multi-tenant : les tests s'exécutent dans le contexte d'un CFA courant.
        // Filament rattache alors automatiquement les données créées à ce CFA
        // (observer `creating`) et sait générer les URL `admin/{tenant}/…`.
        if (Schema::hasTable('organisations')) {
            $tenant = Organisation::query()->firstOrCreate(
                ['slug' => 'cfa-test'],
                ['nom' => 'CFA de test', 'actif' => true],
            );

            // Panel courant + tenant : indispensable pour que Resource::getUrl()
            // (appelé y compris hors requête HTTP, ex. widgets/services) sache
            // renseigner le paramètre {tenant} des routes du panel.
            Filament::setCurrentPanel('admin');
            Filament::setTenant($tenant, isQuiet: true);

            // setTenant(isQuiet) n'émet pas l'événement TenantSet : on fixe donc
            // nous-mêmes le paramètre {tenant} par défaut, indispensable aux
            // appels route('filament.admin.…') des services testés directement.
            URL::defaults(['tenant' => $tenant->getRouteKey()]);
        }
    }
}
