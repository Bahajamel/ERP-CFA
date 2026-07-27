<?php

namespace App\Filament\Editeur\Resources\DemoRequests\Pages;

use App\Filament\Editeur\Resources\DemoRequests\DemoRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDemoRequests extends ListRecords
{
    protected static string $resource = DemoRequestResource::class;

    /** Aucune action d'en-tête : les demandes viennent du site vitrine. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
