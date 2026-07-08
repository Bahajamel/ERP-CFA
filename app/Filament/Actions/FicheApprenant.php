<?php

namespace App\Filament\Actions;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Enums\PresenceStatut;
use App\Models\Candidate;
use App\Models\Presence;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

/**
 * Fiche profil de l'apprenant en pop-up (partie scolarité) : photo, identité,
 * formation et classes, assiduité, documents pédagogiques (bulletins, notes…)
 * avec dépôt direct dans la GED. S'ouvre d'un clic sur le nom de l'apprenant.
 */
class FicheApprenant
{
    public static function action(): Action
    {
        return Action::make('ficheApprenant')
            ->label('Fiche')
            ->modalHeading(fn (Candidate $record): string => 'Fiche apprenant — '.$record->nom_complet)
            ->modalContent(fn (Candidate $record) => view('filament.apprenant-fiche', [
                'apprenant' => $record->loadMissing(['formationVisee', 'promotions.formation']),
                'documents' => $record->documents()
                    ->where('type', DocumentType::DocumentPedagogique)
                    ->latest()
                    ->get(),
                'assiduite' => self::assiduite($record),
            ]))
            ->schema([
                Section::make('Compléter la fiche')
                    ->description('Photo de profil et documents pédagogiques (bulletins, relevés de notes…) archivés dans la GED.')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Photo de profil')
                            ->image()
                            ->maxSize(4096)
                            ->disk('public'),
                        FileUpload::make('fichiers')
                            ->label('Documents pédagogiques')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('noms')
                            ->disk('public'),
                    ]),
            ])
            ->action(function (array $data, Candidate $record): void {
                if (filled($data['photo'] ?? null)) {
                    $record->addMediaFromDisk($data['photo'], 'public')->toMediaCollection('photo');
                }

                foreach ($data['fichiers'] ?? [] as $chemin) {
                    $document = $record->documents()->create([
                        'type' => DocumentType::DocumentPedagogique,
                        'statut' => DocumentStatut::Recu,
                        'nom_fichier' => $data['noms'][$chemin] ?? basename($chemin),
                        'version' => 1,
                        'uploaded_by' => Auth::id(),
                    ]);

                    $document->addMediaFromDisk($chemin, 'public')->toMediaCollection('fichier');
                }

                if (filled($data['photo'] ?? null) || filled($data['fichiers'] ?? [])) {
                    Notification::make()->success()->title('Fiche apprenant mise à jour')->send();
                }
            })
            ->modalSubmitActionLabel('Enregistrer')
            ->modalCancelActionLabel('Fermer')
            ->modalWidth('3xl');
    }

    /** Taux de présence global de l'apprenant (présences renseignées uniquement). */
    private static function assiduite(Candidate $record): ?int
    {
        $renseignees = Presence::where('candidate_id', $record->id)
            ->where('statut', '!=', PresenceStatut::NonRenseigne->value)
            ->count();

        if ($renseignees === 0) {
            return null;
        }

        $presents = Presence::where('candidate_id', $record->id)
            ->whereIn('statut', array_map(fn (PresenceStatut $s) => $s->value, PresenceStatut::presents()))
            ->count();

        return (int) round($presents / $renseignees * 100);
    }
}
