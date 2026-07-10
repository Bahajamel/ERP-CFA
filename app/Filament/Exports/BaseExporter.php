<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Base commune des exports : notification de fin en français.
 *
 * La traçabilité de l'export (auteur + date) est portée nativement par la table
 * `exports` de Filament (colonnes user_id / created_at), ce qui satisfait la
 * règle P1-19-2 « conserver date de génération + auteur ».
 */
abstract class BaseExporter extends Exporter
{
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export terminé : '.Number::format($export->successful_rows).' ligne(s) exportée(s).';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed).' ligne(s) en échec.';
        }

        return $body;
    }
}
