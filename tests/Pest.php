<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuration Pest
|--------------------------------------------------------------------------
| Lie la classe TestCase de l'application aux tests du dossier Feature, afin
| de disposer de l'application Laravel (base de données, auth, etc.).
*/

uses(TestCase::class)->in('Feature');
