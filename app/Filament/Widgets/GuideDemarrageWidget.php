<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Matchings\MatchingResource;
use App\Filament\Resources\Needs\NeedResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use Filament\Widgets\Widget;

/**
 * Guide de démarrage affiché en tête de l'accueil : explique le cycle de vie
 * d'un apprenant (un module = une étape) et donne un accès direct à chaque étape.
 * L'antidote au « je ne sais pas par où commencer ».
 */
class GuideDemarrageWidget extends Widget
{
    protected string $view = 'filament.widgets.guide-demarrage';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    /** Les étapes du parcours, avec un lien vers le module si l'accès est permis. */
    public function steps(): array
    {
        $etapes = [
            [1, 'Candidat', 'La fiche de la personne à former (identité, contact, formation visée).', CandidateResource::class],
            [2, 'Admission', 'Vérifier les pièces obligatoires du dossier et valider l\'entrée au CFA.', AdmissionResource::class],
            [3, 'Entreprise', 'Les entreprises partenaires qui accueillent les apprentis.', CompanyResource::class],
            [4, 'Besoin', 'Les postes en alternance que les entreprises veulent pourvoir.', NeedResource::class],
            [5, 'Matching', 'Rapprocher un candidat du bon besoin d\'entreprise.', MatchingResource::class],
            [6, 'Contrat', 'Établir le contrat d\'apprentissage (CERFA) et le faire signer.', ContractResource::class],
            [7, 'Dossier OPCO', 'Faire financer le contrat par l\'OPCO et suivre les versements.', OpcoFileResource::class],
        ];

        return array_map(fn (array $e) => [
            'num' => $e[0],
            'title' => $e[1],
            'role' => $e[2],
            'url' => $e[3]::canAccess() ? $e[3]::getUrl() : null,
        ], $etapes);
    }

    /** Lien direct « Ajouter un apprenant » (null si l'utilisateur n'y a pas accès). */
    public function nouvelApprenantUrl(): ?string
    {
        return CandidateResource::canAccess() ? CandidateResource::getUrl('create') : null;
    }
}
