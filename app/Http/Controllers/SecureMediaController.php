<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Formation;
use App\Models\Matching;
use App\Models\OpcoFile;
use App\Models\Presence;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une pièce sensible (données personnelles / NIR) stockée sur un disque
 * privé. L'accès est verrouillé à TROIS niveaux :
 *   - `auth`   : une session ERP valide est obligatoire (middleware) ;
 *   - `signed` : l'URL doit porter une signature non expirée émise par nous
 *                (cf. App\Support\SecureMedia) — impossible à deviner ou forger ;
 *   - permission : l'utilisateur doit détenir la permission du module propriétaire
 *                de la pièce (ex. une carte vitale de candidat exige `access_candidates`).
 *                Sans ce 3ᵉ niveau, une URL signée qui fuit serait rejouable par
 *                n'importe quelle session, même un rôle sans droit sur ces données.
 *
 * Le fichier est streamé « inline » (aperçu navigateur) directement depuis son
 * disque, avec `nosniff` pour empêcher le navigateur de réinterpréter le type MIME.
 */
class SecureMediaController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        $this->autoriserAcces($media);

        $disque = Storage::disk($media->disk);
        $chemin = $media->getPathRelativeToRoot();

        abort_unless($disque->exists($chemin), 404);

        return $disque->response($chemin, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Vérifie que l'utilisateur a le droit d'accéder à CE média précis (403 sinon). */
    private function autoriserAcces(Media $media): void
    {
        $user = auth()->user();
        $permissions = $this->permissionsRequises($media->model);

        abort_unless(
            $user !== null && collect($permissions)->contains(fn (string $p): bool => $user->can($p)),
            403,
        );
    }

    /**
     * Permissions acceptables selon le modèle propriétaire de la pièce.
     * L'utilisateur doit en détenir AU MOINS UNE.
     *
     * @return array<int, string>
     */
    private function permissionsRequises(?Model $owner): array
    {
        return match (true) {
            $owner instanceof Candidate => ['access_candidates'],
            $owner instanceof Contract => ['access_contracts'],
            $owner instanceof OpcoFile => ['access_opco'],
            $owner instanceof Presence => ['access_attendance'],
            $owner instanceof Matching => ['access_matching'],
            // Une pièce de GED : accès documentaire OU accès au module du dossier lié.
            $owner instanceof Document => array_values(array_unique(array_filter([
                'access_documents',
                $this->permissionPourType($owner->documentable_type),
            ]))),
            // Repli prudent (profil CFA, types non mappés) : réservé à la GED.
            default => ['access_documents'],
        };
    }

    private function permissionPourType(?string $type): ?string
    {
        return match ($type) {
            Candidate::class => 'access_candidates',
            Contract::class => 'access_contracts',
            OpcoFile::class => 'access_opco',
            Company::class => 'access_companies',
            Promotion::class, Formation::class => 'access_formations',
            default => null,
        };
    }
}
