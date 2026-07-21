<?php

namespace App\Support\Assistant;

use App\Models\FaqBot;
use App\Models\FaqEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recherche lexicale dans la FAQ d'UN assistant. Aucune IA, aucun appel externe :
 * on note chaque question selon les mots employés, et on renvoie les meilleures.
 *
 * L'algorithme est celui, éprouvé, de {@see BaseFaq} — seule la source change
 * (les entrées viennent désormais de la base, donc modifiables sans code) :
 *   mot-clé exact       → +3
 *   mot-clé approchant  → +2   (l'un préfixe l'autre : pluriels, conjugaisons)
 *   mot du titre        → +1
 *
 * La recherche est TOUJOURS bornée à l'assistant courant : une question sur les
 * contrats posée à l'assistant Scolarité ne remonte rien, conformément à la
 * demande (« ne pas répondre hors du module »).
 */
class RechercheFaq
{
    /** Mots vides, ignorés dans la comparaison. */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'a', 'au', 'aux', 'et', 'ou', 'où',
        'je', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'mon', 'ma', 'mes', 'se', 'ce',
        'cet', 'cette', 'ces', 'est', 'sont', 'dans', 'pour', 'sur', 'avec', 'par', 'que', 'qui',
        'quoi', 'quel', 'quelle', 'quels', 'quelles', 'comment', 'pourquoi', 'faire', 'fait',
        'veux', 'peux', 'puis', 'dois', 'y', 'en', 'sa', 'son', 'ses', 'the', 'to', 'me', 'te',
    ];

    /**
     * Meilleures réponses de cet assistant pour la question posée.
     *
     * @return Collection<int, FaqEntry>
     */
    public static function rechercher(FaqBot $bot, string $message, int $limite = 3): Collection
    {
        $tokens = self::tokeniser($message);

        if ($tokens === []) {
            return collect();
        }

        return $bot->entrees()->actif()->get()
            ->map(fn (FaqEntry $entree): array => ['score' => self::scorer($entree, $tokens), 'entree' => $entree])
            ->filter(fn (array $r): bool => $r['score'] > 0)
            ->sortByDesc('score')
            ->take($limite)
            ->pluck('entree')
            ->values();
    }

    /**
     * Questions proposées d'emblée par l'assistant (les premières de sa liste,
     * dans l'ordre défini par l'administrateur).
     *
     * @return list<string>
     */
    public static function suggestions(FaqBot $bot, int $limite = 4): array
    {
        return $bot->entrees()->actif()->limit($limite)->pluck('question')->all();
    }

    /** Score de pertinence d'une entrée face aux mots de la question. */
    private static function scorer(FaqEntry $entree, array $tokens): int
    {
        $motsCles = array_map([self::class, 'normaliser'], $entree->keywords ?? []);
        $motsTitre = self::tokeniser($entree->question);
        $score = 0;

        foreach ($tokens as $token) {
            foreach ($motsCles as $cle) {
                if ($token === $cle) {
                    $score += 3;
                } elseif (self::proche($token, $cle)) {
                    $score += 2;
                }
            }

            if (in_array($token, $motsTitre, true)) {
                $score += 1;
            }
        }

        return $score;
    }

    /** Deux mots « proches » : l'un préfixe l'autre (tolère pluriels/conjugaisons). */
    private static function proche(string $a, string $b): bool
    {
        if (mb_strlen($a) < 4 || mb_strlen($b) < 4) {
            return false;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    /**
     * Découpe un texte en mots significatifs, normalisés et hors mots vides.
     *
     * @return list<string>
     */
    private static function tokeniser(string $texte): array
    {
        $mots = preg_split('/[^a-z0-9]+/', self::normaliser($texte), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $mots,
            fn (string $mot): bool => mb_strlen($mot) >= 2 && ! in_array($mot, self::STOPWORDS, true),
        ));
    }

    /** Minuscule + suppression des accents (recherche insensible casse/accents). */
    private static function normaliser(string $texte): string
    {
        return Str::lower(Str::ascii($texte));
    }
}
