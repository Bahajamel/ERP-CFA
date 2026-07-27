<?php

namespace App\StateMachine;

use RuntimeException;

/**
 * Levée lorsqu'une transition d'état est refusée (structure non autorisée
 * ou règle métier bloquante). Le message est destiné à l'utilisateur.
 */
class InvalidTransitionException extends RuntimeException {}
