<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de téléphone au format international (E.164) : indicatif pays
 * obligatoire (« + » suivi de l'indicatif), ex. +33 6 12 34 56 78.
 *
 * Les espaces, points, tirets et parenthèses de saisie sont tolérés (ils sont
 * retirés avant contrôle). La valeur vide passe : l'obligation éventuelle est
 * gérée en amont (required / required_without), pas ici.
 */
class TelephoneInternational implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        // Séparateurs de confort retirés avant contrôle du format.
        $normalise = preg_replace('/[\s.\-()]/', '', (string) $value);

        // « + » puis indicatif pays (1-9) puis 7 à 14 chiffres → 8 à 15 chiffres
        // au total (norme E.164). Rejette « 0612… » sans indicatif.
        if (! is_string($normalise) || ! preg_match('/^\+[1-9]\d{7,14}$/', $normalise)) {
            $fail('Le numéro doit être au format international avec l\'indicatif pays, ex. +33 6 12 34 56 78.');
        }
    }
}
