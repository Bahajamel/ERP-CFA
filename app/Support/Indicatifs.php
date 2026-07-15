<?php

namespace App\Support;

use App\Rules\TelephoneInternational;

/**
 * Indicatifs téléphoniques par pays, pour le sélecteur de pays accolé aux
 * champs téléphone. La valeur stockée reste le numéro complet au format
 * international (ex. +33 6 12 34 56 78), validé par
 * {@see TelephoneInternational} ; l'indicatif n'est qu'une aide de
 * saisie qui préfixe/complète ce champ.
 */
class Indicatifs
{
    /**
     * Pays proposés (France en tête). Chaque entrée : [indicatif, pays, drapeau].
     * Indicatifs volontairement uniques (clé du select).
     *
     * @return array<int, array{code: string, pays: string, drapeau: string}>
     */
    public static function liste(): array
    {
        return [
            ['code' => '+33', 'pays' => 'France', 'drapeau' => '🇫🇷'],
            ['code' => '+32', 'pays' => 'Belgique', 'drapeau' => '🇧🇪'],
            ['code' => '+41', 'pays' => 'Suisse', 'drapeau' => '🇨🇭'],
            ['code' => '+352', 'pays' => 'Luxembourg', 'drapeau' => '🇱🇺'],
            ['code' => '+49', 'pays' => 'Allemagne', 'drapeau' => '🇩🇪'],
            ['code' => '+34', 'pays' => 'Espagne', 'drapeau' => '🇪🇸'],
            ['code' => '+39', 'pays' => 'Italie', 'drapeau' => '🇮🇹'],
            ['code' => '+351', 'pays' => 'Portugal', 'drapeau' => '🇵🇹'],
            ['code' => '+44', 'pays' => 'Royaume-Uni', 'drapeau' => '🇬🇧'],
            ['code' => '+31', 'pays' => 'Pays-Bas', 'drapeau' => '🇳🇱'],
            ['code' => '+212', 'pays' => 'Maroc', 'drapeau' => '🇲🇦'],
            ['code' => '+213', 'pays' => 'Algérie', 'drapeau' => '🇩🇿'],
            ['code' => '+216', 'pays' => 'Tunisie', 'drapeau' => '🇹🇳'],
            ['code' => '+221', 'pays' => 'Sénégal', 'drapeau' => '🇸🇳'],
            ['code' => '+225', 'pays' => "Côte d'Ivoire", 'drapeau' => '🇨🇮'],
            ['code' => '+237', 'pays' => 'Cameroun', 'drapeau' => '🇨🇲'],
            ['code' => '+1', 'pays' => 'États-Unis / Canada', 'drapeau' => '🇺🇸'],
        ];
    }

    /**
     * Options du select : indicatif => « 🇫🇷 France (+33) ».
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::liste() as $i) {
            $options[$i['code']] = "{$i['drapeau']} {$i['pays']} ({$i['code']})";
        }

        return $options;
    }

    /** Indicatif par défaut (France). */
    public static function defaut(): string
    {
        return '+33';
    }

    /**
     * Partie « nationale » d'un numéro : on retire l'indicatif international
     * présent, sinon le préfixe national « 0 ». Les séparateurs sont retirés.
     */
    public static function national(?string $telephone): string
    {
        $brut = trim((string) $telephone);

        if ($brut === '') {
            return '';
        }

        $norm = (string) preg_replace('/[\s.\-()]/', '', $brut);

        // Indicatif international déjà présent (le plus long d'abord).
        foreach (self::codesParLongueur() as $code) {
            if (str_starts_with($norm, $code)) {
                return substr($norm, strlen($code));
            }
        }

        // Préfixe national « 0 » (droppé quand on ajoute l'indicatif pays).
        if (str_starts_with($norm, '0')) {
            return substr($norm, 1);
        }

        return $norm;
    }

    /**
     * Applique un indicatif à un numéro saisi (aide de saisie côté formulaire) :
     * remplace l'indicatif éventuel, garde la partie nationale. Renvoie « +33 »
     * si aucun numéro n'est encore saisi.
     */
    public static function appliquer(?string $telephone, ?string $code): string
    {
        $code = $code ?: self::defaut();
        $national = self::national($telephone);

        return $national === '' ? $code.' ' : $code.' '.$national;
    }

    /**
     * Combine un indicatif choisi et un numéro pour obtenir la valeur finale
     * (contrôleurs publics). Un numéro déjà international (« + ») est conservé tel
     * quel ; sinon on préfixe l'indicatif. Le vide reste vide (géré par required).
     */
    public static function combiner(?string $telephone, ?string $code): ?string
    {
        $brut = trim((string) $telephone);

        if ($brut === '') {
            return $telephone;
        }

        if (str_starts_with((string) preg_replace('/[\s.\-()]/', '', $brut), '+')) {
            return $brut;
        }

        return ($code ?: self::defaut()).' '.self::national($brut);
    }

    /**
     * Détecte l'indicatif d'un numéro international stocké (pour présélectionner
     * le pays en édition). Repli sur France.
     */
    public static function detecter(?string $telephone): string
    {
        $norm = (string) preg_replace('/[\s.\-()]/', '', (string) $telephone);

        foreach (self::codesParLongueur() as $code) {
            if (str_starts_with($norm, $code)) {
                return $code;
            }
        }

        return self::defaut();
    }

    /**
     * Indicatifs triés du plus long au plus court (pour un préfixe non ambigu,
     * ex. +352 avant +3).
     *
     * @return array<int, string>
     */
    private static function codesParLongueur(): array
    {
        $codes = array_column(self::liste(), 'code');

        usort($codes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $codes;
    }
}
