<?php

namespace App\Livret;

use RuntimeException;

/** Échec d'appel au service de génération LivretRS (non configuré, injoignable, erreur). */
class LivretRsException extends RuntimeException {}
