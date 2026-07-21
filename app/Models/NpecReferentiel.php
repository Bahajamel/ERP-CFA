<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une entrée du référentiel NPEC (France Compétences) : le niveau de prise en
 * charge annuel d'une certification (RNCP), éventuellement modulé par branche
 * (IDCC). Donnée nationale partagée — pas de rattachement CFA.
 *
 * @property string $code_rncp
 * @property ?string $code_idcc
 * @property float $npec_annuel
 * @property ?string $libelle
 * @property ?string $source
 */
class NpecReferentiel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'npec_annuel' => 'decimal:2',
            'date_valeur' => 'date',
        ];
    }
}
