<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Site vitrine public de Meridian CFA (page commerciale servie sur `/`).
 *
 * Ne touche ni à l'ERP (Filament), ni à l'authentification, ni aux données des
 * CFA : ce sont des pages publiques statiques. Le contenu légal reste neutre —
 * l'identité juridique réelle est marquée « [à compléter] », jamais inventée.
 */
class VitrineController extends Controller
{
    public function accueil(): View
    {
        return view('vitrine.accueil');
    }

    public function mentions(): View
    {
        return view('vitrine.legal', [
            'titre' => 'Mentions légales',
            'sections' => [
                [
                    'titre' => 'Éditeur du site',
                    'paragraphes' => [
                        'Le présent site présente la solution logicielle Meridian CFA, ERP de gestion destiné aux CFA et organismes de formation.',
                        'Éditeur : <strong>[à compléter : raison sociale]</strong> — <strong>[à compléter : forme juridique et capital]</strong>.',
                        'Siège social : <strong>[à compléter : adresse]</strong>. SIRET : <strong>[à compléter]</strong>.',
                        'Directeur de la publication : <strong>[à compléter]</strong>. Contact : <strong>[à compléter : adresse e-mail]</strong>.',
                    ],
                ],
                [
                    'titre' => 'Hébergement',
                    'paragraphes' => [
                        'Le site et l\'application sont hébergés par <strong>[à compléter : nom et adresse de l\'hébergeur]</strong>.',
                    ],
                ],
                [
                    'titre' => 'Propriété intellectuelle',
                    'paragraphes' => [
                        'L\'ensemble des contenus présents sur ce site (textes, visuels, logo, marque « Meridian CFA ») est protégé par le droit de la propriété intellectuelle. Toute reproduction sans autorisation préalable est interdite.',
                    ],
                ],
                [
                    'titre' => 'Responsabilité',
                    'paragraphes' => [
                        'Les informations diffusées sur ce site le sont à titre indicatif. L\'éditeur s\'efforce d\'en assurer l\'exactitude mais ne saurait être tenu responsable d\'éventuelles erreurs ou omissions.',
                    ],
                ],
            ],
        ]);
    }

    public function confidentialite(): View
    {
        return view('vitrine.legal', [
            'titre' => 'Politique de confidentialité',
            'sections' => [
                [
                    'titre' => 'Données collectées',
                    'paragraphes' => [
                        'Lorsque vous remplissez le formulaire de demande de démonstration, nous collectons les informations que vous fournissez : nom, prénom, fonction, établissement, adresse e-mail professionnelle, téléphone, ainsi que les précisions sur votre besoin.',
                        'Ces données sont utilisées uniquement pour répondre à votre demande et vous présenter la solution.',
                    ],
                ],
                [
                    'titre' => 'Finalité et base légale',
                    'paragraphes' => [
                        'Le traitement de vos données repose sur votre consentement, recueilli lors de l\'envoi du formulaire. Elles ne sont ni revendues, ni cédées à des tiers à des fins commerciales.',
                    ],
                ],
                [
                    'titre' => 'Durée de conservation',
                    'paragraphes' => [
                        'Vos données sont conservées le temps nécessaire au traitement de votre demande, puis pendant la durée légale applicable, avant suppression ou anonymisation.',
                    ],
                ],
                [
                    'titre' => 'Vos droits',
                    'paragraphes' => [
                        'Conformément à la réglementation applicable, vous disposez d\'un droit d\'accès, de rectification, d\'effacement et d\'opposition sur vos données. Pour l\'exercer, contactez <strong>[à compléter : adresse e-mail du responsable de traitement]</strong>.',
                    ],
                ],
                [
                    'titre' => 'Cookies',
                    'paragraphes' => [
                        'Le site vitrine ne dépose pas de cookies de suivi publicitaire. Seuls les cookies strictement nécessaires au fonctionnement peuvent être utilisés.',
                    ],
                ],
            ],
        ]);
    }
}
