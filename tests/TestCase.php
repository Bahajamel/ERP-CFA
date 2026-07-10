<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
    }
}
