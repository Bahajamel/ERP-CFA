<?php

namespace App\Parcours;

use RuntimeException;

/**
 * Étape du cycle apprenant refusée (prérequis manquant, doublon…).
 * Le message est destiné à l'utilisateur final.
 */
class CycleBloqueException extends RuntimeException
{
}
