<?php

namespace App\Support;

use App\Rules\TelephoneInternational;

/**
 * Indicatifs téléphoniques de tous les pays, pour le sélecteur « Pays » accolé
 * aux champs téléphone. La valeur stockée reste le numéro complet au format
 * international (ex. +33 6 12 34 56 78), validé par
 * {@see TelephoneInternational} ; le sélecteur n'est qu'une aide de
 * saisie qui préfixe/complète ce champ.
 *
 * La valeur du select est le **code ISO 3166-1 alpha-2** (unique), car un même
 * indicatif peut couvrir plusieurs pays (+1, +7…). Le drapeau est calculé depuis
 * ce code ISO (indicateurs régionaux Unicode), sans emoji codé en dur.
 */
class Indicatifs
{
    /** Code ISO du pays par défaut (France). */
    public static function defaut(): string
    {
        return 'FR';
    }

    /**
     * Tous les pays : code ISO 3166-1 alpha-2, nom français, indicatif.
     * France en tête, puis ordre alphabétique.
     *
     * @return array<int, array{iso: string, pays: string, code: string}>
     */
    public static function pays(): array
    {
        return [
            ['iso' => 'FR', 'pays' => 'France', 'code' => '+33'],
            ['iso' => 'AF', 'pays' => 'Afghanistan', 'code' => '+93'],
            ['iso' => 'ZA', 'pays' => 'Afrique du Sud', 'code' => '+27'],
            ['iso' => 'AL', 'pays' => 'Albanie', 'code' => '+355'],
            ['iso' => 'DZ', 'pays' => 'Algérie', 'code' => '+213'],
            ['iso' => 'DE', 'pays' => 'Allemagne', 'code' => '+49'],
            ['iso' => 'AD', 'pays' => 'Andorre', 'code' => '+376'],
            ['iso' => 'AO', 'pays' => 'Angola', 'code' => '+244'],
            ['iso' => 'AI', 'pays' => 'Anguilla', 'code' => '+1'],
            ['iso' => 'AG', 'pays' => 'Antigua-et-Barbuda', 'code' => '+1'],
            ['iso' => 'SA', 'pays' => 'Arabie saoudite', 'code' => '+966'],
            ['iso' => 'AR', 'pays' => 'Argentine', 'code' => '+54'],
            ['iso' => 'AM', 'pays' => 'Arménie', 'code' => '+374'],
            ['iso' => 'AW', 'pays' => 'Aruba', 'code' => '+297'],
            ['iso' => 'AU', 'pays' => 'Australie', 'code' => '+61'],
            ['iso' => 'AT', 'pays' => 'Autriche', 'code' => '+43'],
            ['iso' => 'AZ', 'pays' => 'Azerbaïdjan', 'code' => '+994'],
            ['iso' => 'BS', 'pays' => 'Bahamas', 'code' => '+1'],
            ['iso' => 'BH', 'pays' => 'Bahreïn', 'code' => '+973'],
            ['iso' => 'BD', 'pays' => 'Bangladesh', 'code' => '+880'],
            ['iso' => 'BB', 'pays' => 'Barbade', 'code' => '+1'],
            ['iso' => 'BE', 'pays' => 'Belgique', 'code' => '+32'],
            ['iso' => 'BZ', 'pays' => 'Belize', 'code' => '+501'],
            ['iso' => 'BJ', 'pays' => 'Bénin', 'code' => '+229'],
            ['iso' => 'BM', 'pays' => 'Bermudes', 'code' => '+1'],
            ['iso' => 'BT', 'pays' => 'Bhoutan', 'code' => '+975'],
            ['iso' => 'BY', 'pays' => 'Biélorussie', 'code' => '+375'],
            ['iso' => 'BO', 'pays' => 'Bolivie', 'code' => '+591'],
            ['iso' => 'BA', 'pays' => 'Bosnie-Herzégovine', 'code' => '+387'],
            ['iso' => 'BW', 'pays' => 'Botswana', 'code' => '+267'],
            ['iso' => 'BR', 'pays' => 'Brésil', 'code' => '+55'],
            ['iso' => 'BN', 'pays' => 'Brunei', 'code' => '+673'],
            ['iso' => 'BG', 'pays' => 'Bulgarie', 'code' => '+359'],
            ['iso' => 'BF', 'pays' => 'Burkina Faso', 'code' => '+226'],
            ['iso' => 'BI', 'pays' => 'Burundi', 'code' => '+257'],
            ['iso' => 'KH', 'pays' => 'Cambodge', 'code' => '+855'],
            ['iso' => 'CM', 'pays' => 'Cameroun', 'code' => '+237'],
            ['iso' => 'CA', 'pays' => 'Canada', 'code' => '+1'],
            ['iso' => 'CV', 'pays' => 'Cap-Vert', 'code' => '+238'],
            ['iso' => 'CL', 'pays' => 'Chili', 'code' => '+56'],
            ['iso' => 'CN', 'pays' => 'Chine', 'code' => '+86'],
            ['iso' => 'CY', 'pays' => 'Chypre', 'code' => '+357'],
            ['iso' => 'CO', 'pays' => 'Colombie', 'code' => '+57'],
            ['iso' => 'KM', 'pays' => 'Comores', 'code' => '+269'],
            ['iso' => 'CG', 'pays' => 'Congo-Brazzaville', 'code' => '+242'],
            ['iso' => 'CD', 'pays' => 'Congo (RDC)', 'code' => '+243'],
            ['iso' => 'KR', 'pays' => 'Corée du Sud', 'code' => '+82'],
            ['iso' => 'KP', 'pays' => 'Corée du Nord', 'code' => '+850'],
            ['iso' => 'CR', 'pays' => 'Costa Rica', 'code' => '+506'],
            ['iso' => 'CI', 'pays' => "Côte d'Ivoire", 'code' => '+225'],
            ['iso' => 'HR', 'pays' => 'Croatie', 'code' => '+385'],
            ['iso' => 'CU', 'pays' => 'Cuba', 'code' => '+53'],
            ['iso' => 'DK', 'pays' => 'Danemark', 'code' => '+45'],
            ['iso' => 'DJ', 'pays' => 'Djibouti', 'code' => '+253'],
            ['iso' => 'DM', 'pays' => 'Dominique', 'code' => '+1'],
            ['iso' => 'EG', 'pays' => 'Égypte', 'code' => '+20'],
            ['iso' => 'AE', 'pays' => 'Émirats arabes unis', 'code' => '+971'],
            ['iso' => 'EC', 'pays' => 'Équateur', 'code' => '+593'],
            ['iso' => 'ER', 'pays' => 'Érythrée', 'code' => '+291'],
            ['iso' => 'ES', 'pays' => 'Espagne', 'code' => '+34'],
            ['iso' => 'EE', 'pays' => 'Estonie', 'code' => '+372'],
            ['iso' => 'US', 'pays' => 'États-Unis', 'code' => '+1'],
            ['iso' => 'ET', 'pays' => 'Éthiopie', 'code' => '+251'],
            ['iso' => 'FJ', 'pays' => 'Fidji', 'code' => '+679'],
            ['iso' => 'FI', 'pays' => 'Finlande', 'code' => '+358'],
            ['iso' => 'GA', 'pays' => 'Gabon', 'code' => '+241'],
            ['iso' => 'GM', 'pays' => 'Gambie', 'code' => '+220'],
            ['iso' => 'GE', 'pays' => 'Géorgie', 'code' => '+995'],
            ['iso' => 'GH', 'pays' => 'Ghana', 'code' => '+233'],
            ['iso' => 'GI', 'pays' => 'Gibraltar', 'code' => '+350'],
            ['iso' => 'GR', 'pays' => 'Grèce', 'code' => '+30'],
            ['iso' => 'GD', 'pays' => 'Grenade', 'code' => '+1'],
            ['iso' => 'GL', 'pays' => 'Groenland', 'code' => '+299'],
            ['iso' => 'GP', 'pays' => 'Guadeloupe', 'code' => '+590'],
            ['iso' => 'GU', 'pays' => 'Guam', 'code' => '+1'],
            ['iso' => 'GT', 'pays' => 'Guatemala', 'code' => '+502'],
            ['iso' => 'GN', 'pays' => 'Guinée', 'code' => '+224'],
            ['iso' => 'GQ', 'pays' => 'Guinée équatoriale', 'code' => '+240'],
            ['iso' => 'GW', 'pays' => 'Guinée-Bissau', 'code' => '+245'],
            ['iso' => 'GY', 'pays' => 'Guyana', 'code' => '+592'],
            ['iso' => 'GF', 'pays' => 'Guyane française', 'code' => '+594'],
            ['iso' => 'HT', 'pays' => 'Haïti', 'code' => '+509'],
            ['iso' => 'HN', 'pays' => 'Honduras', 'code' => '+504'],
            ['iso' => 'HK', 'pays' => 'Hong Kong', 'code' => '+852'],
            ['iso' => 'HU', 'pays' => 'Hongrie', 'code' => '+36'],
            ['iso' => 'IN', 'pays' => 'Inde', 'code' => '+91'],
            ['iso' => 'ID', 'pays' => 'Indonésie', 'code' => '+62'],
            ['iso' => 'IQ', 'pays' => 'Irak', 'code' => '+964'],
            ['iso' => 'IR', 'pays' => 'Iran', 'code' => '+98'],
            ['iso' => 'IE', 'pays' => 'Irlande', 'code' => '+353'],
            ['iso' => 'IS', 'pays' => 'Islande', 'code' => '+354'],
            ['iso' => 'IL', 'pays' => 'Israël', 'code' => '+972'],
            ['iso' => 'IT', 'pays' => 'Italie', 'code' => '+39'],
            ['iso' => 'JM', 'pays' => 'Jamaïque', 'code' => '+1'],
            ['iso' => 'JP', 'pays' => 'Japon', 'code' => '+81'],
            ['iso' => 'JO', 'pays' => 'Jordanie', 'code' => '+962'],
            ['iso' => 'KZ', 'pays' => 'Kazakhstan', 'code' => '+7'],
            ['iso' => 'KE', 'pays' => 'Kenya', 'code' => '+254'],
            ['iso' => 'KG', 'pays' => 'Kirghizistan', 'code' => '+996'],
            ['iso' => 'KI', 'pays' => 'Kiribati', 'code' => '+686'],
            ['iso' => 'KW', 'pays' => 'Koweït', 'code' => '+965'],
            ['iso' => 'LA', 'pays' => 'Laos', 'code' => '+856'],
            ['iso' => 'LS', 'pays' => 'Lesotho', 'code' => '+266'],
            ['iso' => 'LV', 'pays' => 'Lettonie', 'code' => '+371'],
            ['iso' => 'LB', 'pays' => 'Liban', 'code' => '+961'],
            ['iso' => 'LR', 'pays' => 'Liberia', 'code' => '+231'],
            ['iso' => 'LY', 'pays' => 'Libye', 'code' => '+218'],
            ['iso' => 'LI', 'pays' => 'Liechtenstein', 'code' => '+423'],
            ['iso' => 'LT', 'pays' => 'Lituanie', 'code' => '+370'],
            ['iso' => 'LU', 'pays' => 'Luxembourg', 'code' => '+352'],
            ['iso' => 'MO', 'pays' => 'Macao', 'code' => '+853'],
            ['iso' => 'MK', 'pays' => 'Macédoine du Nord', 'code' => '+389'],
            ['iso' => 'MG', 'pays' => 'Madagascar', 'code' => '+261'],
            ['iso' => 'MY', 'pays' => 'Malaisie', 'code' => '+60'],
            ['iso' => 'MW', 'pays' => 'Malawi', 'code' => '+265'],
            ['iso' => 'MV', 'pays' => 'Maldives', 'code' => '+960'],
            ['iso' => 'ML', 'pays' => 'Mali', 'code' => '+223'],
            ['iso' => 'MT', 'pays' => 'Malte', 'code' => '+356'],
            ['iso' => 'MA', 'pays' => 'Maroc', 'code' => '+212'],
            ['iso' => 'MQ', 'pays' => 'Martinique', 'code' => '+596'],
            ['iso' => 'MU', 'pays' => 'Maurice', 'code' => '+230'],
            ['iso' => 'MR', 'pays' => 'Mauritanie', 'code' => '+222'],
            ['iso' => 'YT', 'pays' => 'Mayotte', 'code' => '+262'],
            ['iso' => 'MX', 'pays' => 'Mexique', 'code' => '+52'],
            ['iso' => 'FM', 'pays' => 'Micronésie', 'code' => '+691'],
            ['iso' => 'MD', 'pays' => 'Moldavie', 'code' => '+373'],
            ['iso' => 'MC', 'pays' => 'Monaco', 'code' => '+377'],
            ['iso' => 'MN', 'pays' => 'Mongolie', 'code' => '+976'],
            ['iso' => 'ME', 'pays' => 'Monténégro', 'code' => '+382'],
            ['iso' => 'MS', 'pays' => 'Montserrat', 'code' => '+1'],
            ['iso' => 'MZ', 'pays' => 'Mozambique', 'code' => '+258'],
            ['iso' => 'MM', 'pays' => 'Birmanie (Myanmar)', 'code' => '+95'],
            ['iso' => 'NA', 'pays' => 'Namibie', 'code' => '+264'],
            ['iso' => 'NR', 'pays' => 'Nauru', 'code' => '+674'],
            ['iso' => 'NP', 'pays' => 'Népal', 'code' => '+977'],
            ['iso' => 'NI', 'pays' => 'Nicaragua', 'code' => '+505'],
            ['iso' => 'NE', 'pays' => 'Niger', 'code' => '+227'],
            ['iso' => 'NG', 'pays' => 'Nigeria', 'code' => '+234'],
            ['iso' => 'NC', 'pays' => 'Nouvelle-Calédonie', 'code' => '+687'],
            ['iso' => 'NZ', 'pays' => 'Nouvelle-Zélande', 'code' => '+64'],
            ['iso' => 'NO', 'pays' => 'Norvège', 'code' => '+47'],
            ['iso' => 'OM', 'pays' => 'Oman', 'code' => '+968'],
            ['iso' => 'UG', 'pays' => 'Ouganda', 'code' => '+256'],
            ['iso' => 'UZ', 'pays' => 'Ouzbékistan', 'code' => '+998'],
            ['iso' => 'PK', 'pays' => 'Pakistan', 'code' => '+92'],
            ['iso' => 'PW', 'pays' => 'Palaos', 'code' => '+680'],
            ['iso' => 'PS', 'pays' => 'Palestine', 'code' => '+970'],
            ['iso' => 'PA', 'pays' => 'Panama', 'code' => '+507'],
            ['iso' => 'PG', 'pays' => 'Papouasie-Nouvelle-Guinée', 'code' => '+675'],
            ['iso' => 'PY', 'pays' => 'Paraguay', 'code' => '+595'],
            ['iso' => 'NL', 'pays' => 'Pays-Bas', 'code' => '+31'],
            ['iso' => 'PE', 'pays' => 'Pérou', 'code' => '+51'],
            ['iso' => 'PH', 'pays' => 'Philippines', 'code' => '+63'],
            ['iso' => 'PL', 'pays' => 'Pologne', 'code' => '+48'],
            ['iso' => 'PF', 'pays' => 'Polynésie française', 'code' => '+689'],
            ['iso' => 'PR', 'pays' => 'Porto Rico', 'code' => '+1'],
            ['iso' => 'PT', 'pays' => 'Portugal', 'code' => '+351'],
            ['iso' => 'QA', 'pays' => 'Qatar', 'code' => '+974'],
            ['iso' => 'RE', 'pays' => 'La Réunion', 'code' => '+262'],
            ['iso' => 'RO', 'pays' => 'Roumanie', 'code' => '+40'],
            ['iso' => 'GB', 'pays' => 'Royaume-Uni', 'code' => '+44'],
            ['iso' => 'RU', 'pays' => 'Russie', 'code' => '+7'],
            ['iso' => 'RW', 'pays' => 'Rwanda', 'code' => '+250'],
            ['iso' => 'KN', 'pays' => 'Saint-Kitts-et-Nevis', 'code' => '+1'],
            ['iso' => 'SM', 'pays' => 'Saint-Marin', 'code' => '+378'],
            ['iso' => 'VC', 'pays' => 'Saint-Vincent-et-les-Grenadines', 'code' => '+1'],
            ['iso' => 'LC', 'pays' => 'Sainte-Lucie', 'code' => '+1'],
            ['iso' => 'SV', 'pays' => 'Salvador', 'code' => '+503'],
            ['iso' => 'WS', 'pays' => 'Samoa', 'code' => '+685'],
            ['iso' => 'ST', 'pays' => 'Sao Tomé-et-Principe', 'code' => '+239'],
            ['iso' => 'SN', 'pays' => 'Sénégal', 'code' => '+221'],
            ['iso' => 'RS', 'pays' => 'Serbie', 'code' => '+381'],
            ['iso' => 'SC', 'pays' => 'Seychelles', 'code' => '+248'],
            ['iso' => 'SL', 'pays' => 'Sierra Leone', 'code' => '+232'],
            ['iso' => 'SG', 'pays' => 'Singapour', 'code' => '+65'],
            ['iso' => 'SK', 'pays' => 'Slovaquie', 'code' => '+421'],
            ['iso' => 'SI', 'pays' => 'Slovénie', 'code' => '+386'],
            ['iso' => 'SO', 'pays' => 'Somalie', 'code' => '+252'],
            ['iso' => 'SD', 'pays' => 'Soudan', 'code' => '+249'],
            ['iso' => 'SS', 'pays' => 'Soudan du Sud', 'code' => '+211'],
            ['iso' => 'LK', 'pays' => 'Sri Lanka', 'code' => '+94'],
            ['iso' => 'SE', 'pays' => 'Suède', 'code' => '+46'],
            ['iso' => 'CH', 'pays' => 'Suisse', 'code' => '+41'],
            ['iso' => 'SR', 'pays' => 'Suriname', 'code' => '+597'],
            ['iso' => 'SZ', 'pays' => 'Eswatini', 'code' => '+268'],
            ['iso' => 'SY', 'pays' => 'Syrie', 'code' => '+963'],
            ['iso' => 'TJ', 'pays' => 'Tadjikistan', 'code' => '+992'],
            ['iso' => 'TW', 'pays' => 'Taïwan', 'code' => '+886'],
            ['iso' => 'TZ', 'pays' => 'Tanzanie', 'code' => '+255'],
            ['iso' => 'TD', 'pays' => 'Tchad', 'code' => '+235'],
            ['iso' => 'CZ', 'pays' => 'Tchéquie', 'code' => '+420'],
            ['iso' => 'TH', 'pays' => 'Thaïlande', 'code' => '+66'],
            ['iso' => 'TL', 'pays' => 'Timor oriental', 'code' => '+670'],
            ['iso' => 'TG', 'pays' => 'Togo', 'code' => '+228'],
            ['iso' => 'TO', 'pays' => 'Tonga', 'code' => '+676'],
            ['iso' => 'TT', 'pays' => 'Trinité-et-Tobago', 'code' => '+1'],
            ['iso' => 'TN', 'pays' => 'Tunisie', 'code' => '+216'],
            ['iso' => 'TM', 'pays' => 'Turkménistan', 'code' => '+993'],
            ['iso' => 'TR', 'pays' => 'Turquie', 'code' => '+90'],
            ['iso' => 'TV', 'pays' => 'Tuvalu', 'code' => '+688'],
            ['iso' => 'UA', 'pays' => 'Ukraine', 'code' => '+380'],
            ['iso' => 'UY', 'pays' => 'Uruguay', 'code' => '+598'],
            ['iso' => 'VU', 'pays' => 'Vanuatu', 'code' => '+678'],
            ['iso' => 'VE', 'pays' => 'Venezuela', 'code' => '+58'],
            ['iso' => 'VN', 'pays' => 'Viêt Nam', 'code' => '+84'],
            ['iso' => 'YE', 'pays' => 'Yémen', 'code' => '+967'],
            ['iso' => 'ZM', 'pays' => 'Zambie', 'code' => '+260'],
            ['iso' => 'ZW', 'pays' => 'Zimbabwe', 'code' => '+263'],
        ];
    }

    /**
     * Drapeau emoji calculé depuis le code ISO 3166-1 alpha-2 (indicateurs
     * régionaux Unicode) — aucun emoji codé en dur.
     */
    public static function drapeau(string $iso): string
    {
        $iso = strtoupper(trim($iso));

        if (! preg_match('/^[A-Z]{2}$/', $iso)) {
            return '🏳';
        }

        return mb_chr(0x1F1E6 + (ord($iso[0]) - 65), 'UTF-8')
            .mb_chr(0x1F1E6 + (ord($iso[1]) - 65), 'UTF-8');
    }

    /**
     * Liste complète prête à l'affichage : chaque entrée porte son drapeau.
     *
     * @return array<int, array{iso: string, pays: string, code: string, drapeau: string}>
     */
    public static function liste(): array
    {
        return array_map(
            fn (array $p): array => [...$p, 'drapeau' => self::drapeau($p['iso'])],
            self::pays(),
        );
    }

    /**
     * Options du select (clé = code ISO unique) : « 🇫🇷 France (+33) ».
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::pays() as $p) {
            $options[$p['iso']] = self::drapeau($p['iso'])." {$p['pays']} ({$p['code']})";
        }

        return $options;
    }

    /** Indicatif (+33) d'un code pays ISO. Repli sur France. */
    public static function code(?string $iso): string
    {
        $iso = strtoupper((string) $iso);

        foreach (self::pays() as $p) {
            if ($p['iso'] === $iso) {
                return $p['code'];
            }
        }

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
     * Aide de saisie côté formulaire : applique l'indicatif du pays ISO choisi
     * au numéro saisi (remplace l'indicatif éventuel, garde la partie nationale).
     * Renvoie « +33 » si aucun numéro n'est encore saisi.
     */
    public static function appliquer(?string $telephone, ?string $iso): string
    {
        $code = self::code($iso);
        $national = self::national($telephone);

        return $national === '' ? $code.' ' : $code.' '.$national;
    }

    /**
     * Combine le pays ISO choisi et le numéro saisi (contrôleurs publics). Un
     * numéro déjà international (« + ») est conservé tel quel ; sinon on préfixe
     * l'indicatif. Le vide reste vide (obligation gérée par required).
     */
    public static function combiner(?string $telephone, ?string $iso): ?string
    {
        $brut = trim((string) $telephone);

        if ($brut === '') {
            return $telephone;
        }

        if (str_starts_with((string) preg_replace('/[\s.\-()]/', '', $brut), '+')) {
            return $brut;
        }

        return self::code($iso).' '.self::national($brut);
    }

    /**
     * Détecte le pays (code ISO) d'un numéro international stocké, pour
     * présélectionner le sélecteur en édition. Indicatif le plus long d'abord ;
     * repli sur France.
     */
    public static function detecter(?string $telephone): string
    {
        $norm = (string) preg_replace('/[\s.\-()]/', '', (string) $telephone);

        foreach (self::codesParLongueur() as $code) {
            if (str_starts_with($norm, $code)) {
                foreach (self::pays() as $p) {
                    if ($p['code'] === $code) {
                        return $p['iso'];
                    }
                }
            }
        }

        return self::defaut();
    }

    /**
     * Indicatifs uniques triés du plus long au plus court (préfixe non ambigu,
     * ex. +352 avant +3).
     *
     * @return array<int, string>
     */
    private static function codesParLongueur(): array
    {
        $codes = array_values(array_unique(array_column(self::pays(), 'code')));

        usort($codes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $codes;
    }
}
