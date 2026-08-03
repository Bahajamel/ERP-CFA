<?php

namespace App\Filament\Actions;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\Seance;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Feuille d'émargement d'une séance : dépôt du scan signé (preuve OPCO /
 * Qualiopi) archivé en GED avec versions — un nouveau dépôt remplace le
 * précédent sans l'effacer (traçabilité). Consultation des versions dans
 * le même pop-up.
 */
class FeuilleEmargementAction
{
    public static function make(): Action
    {
        return Action::make('feuilleEmargement')
            ->label("Feuille d'émargement")
            ->icon('heroicon-o-document-text')
            ->color(fn (Seance $record): string => $record->feuilleEmargement() ? 'success' : 'gray')
            ->modalHeading(fn (Seance $record): string => "Feuille d'émargement — "
                .($record->libelle ?? 'Séance')
                .' du '.$record->date->format('d/m/Y'))
            ->modalDescription('Téléchargez la fiche à imprimer et faire signer, ou déposez le scan de la feuille signée.')
            ->modalContent(fn (Seance $record) => view('filament.feuille-emargement', [
                'feuilles' => $record->documents()
                    ->where('type', DocumentType::FeuilleEmargement)
                    ->orderByDesc('version')
                    ->orderByDesc('id')
                    ->get(),
            ]))
            // Étape 1 « Générer » proposée dans le MÊME modal (plus de bouton séparé).
            ->extraModalFooterActions(fn (Seance $record): array => [
                FicheEmargementPdfAction::make()
                    ->record($record)
                    ->label('Télécharger la fiche (PDF)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray'),
            ])
            ->schema([
                FileUpload::make('fichier')
                    ->label('Scan de la feuille signée')
                    ->helperText('PDF ou photo (JPG, PNG) — max 10 Mo. Un nouveau dépôt crée une nouvelle version, sans effacer la précédente.')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->storeFileNamesIn('nom_original'),
            ])
            ->action(function (array $data, Seance $record, $livewire): void {
                if (blank($data['fichier'] ?? null)) {
                    return; // simple consultation, rien à enregistrer
                }

                $precedente = $record->feuilleEmargement();

                $document = $record->documents()->create([
                    'type' => DocumentType::FeuilleEmargement,
                    'statut' => DocumentStatut::Recu,
                    'nom_fichier' => $data['nom_original'] ?? basename($data['fichier']),
                    'version' => ($precedente?->version ?? 0) + 1,
                    'previous_version_id' => $precedente?->id,
                    'uploaded_by' => Auth::id(),
                ]);

                $document->addMediaFromDisk($data['fichier'], 'public')->toMediaCollection('fichier');

                // Synchronise les autres composants ouverts sur la même séance
                // (bouton d'en-tête de la page ↔ section Émargement).
                $livewire->dispatch('feuille-emargement-maj');

                Notification::make()
                    ->success()
                    ->title("Feuille d'émargement archivée")
                    ->body('Version '.$document->version.' enregistrée dans la GED.')
                    ->send();
            })
            ->modalSubmitActionLabel('Déposer le scan')
            ->modalCancelActionLabel('Fermer')
            ->modalWidth('lg');
    }
}
