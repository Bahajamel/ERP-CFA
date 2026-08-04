<?php

namespace App\Support;

use App\Models\Organisation;
use Filament\Support\Colors\Color;

/**
 * Identité visuelle d'un CFA (white-label) réutilisable HORS du panneau Filament :
 * portail apprenant/entreprise, pages publiques… Le panneau admin conserve sa
 * propre injection (variables Filament + sidebar) ; ce helper expose la couleur,
 * la palette OKLCH et le logo pour un layout Blade classique.
 */
class IdentiteVisuelle
{
    /** Couleur d'accent par défaut (indigo) si le CFA n'en a pas choisi. */
    public const COULEUR_DEFAUT = '#4f46e5';

    /** Couleur d'accent du CFA (celle choisie, sinon la couleur par défaut). */
    public static function couleur(Organisation $cfa): string
    {
        return filled($cfa->couleur_primaire) ? $cfa->couleur_primaire : self::COULEUR_DEFAUT;
    }

    /**
     * Palette de nuances 50→950 (OKLCH) dérivée de la couleur du CFA.
     *
     * @return array<int, string>
     */
    public static function palette(Organisation $cfa): array
    {
        return Color::hex(self::couleur($cfa));
    }

    /**
     * Bloc de variables CSS « --primary-50 … --primary-950 » à poser sur :root,
     * pour teinter un layout public aux couleurs du CFA.
     */
    public static function variablesCss(Organisation $cfa): string
    {
        $vars = '';
        foreach (self::palette($cfa) as $nuance => $valeur) {
            $vars .= "--primary-{$nuance}:{$valeur};";
        }

        return $vars;
    }

    /**
     * Logo du CFA en data-URI base64. La collection « logo » peut vivre sur un
     * disque privé (pas d'URL publique) : on lit le fichier côté serveur, comme
     * pour les PDF. Null si aucun logo lisible.
     */
    public static function logoDataUri(Organisation $cfa): ?string
    {
        $media = $cfa->getFirstMedia('logo');

        if ($media === null || ! is_file($media->getPath())) {
            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode(file_get_contents($media->getPath()));
    }
}
