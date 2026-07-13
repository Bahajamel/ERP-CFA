<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert une pièce sensible (données personnelles / NIR) stockée sur un disque
 * privé. L'accès est verrouillé à deux niveaux, appliqués par les middlewares
 * de la route `documents.securise` :
 *   - `auth`   : une session ERP valide est obligatoire ;
 *   - `signed` : l'URL doit porter une signature non expirée émise par nous
 *                (cf. App\Support\SecureMedia) — impossible à deviner ou forger.
 *
 * Le fichier est streamé « inline » (aperçu navigateur) directement depuis son
 * disque, ce qui fonctionne aussi bien en local qu'avec un bucket objet (S3).
 */
class SecureMediaController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        $disque = Storage::disk($media->disk);
        $chemin = $media->getPathRelativeToRoot();

        abort_unless($disque->exists($chemin), 404);

        return $disque->response($chemin, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
        ]);
    }
}
