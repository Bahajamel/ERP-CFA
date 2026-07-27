<?php

namespace App\Filament\Resources\Admissions;

use App\Enums\AdmissionStatut;
use App\Enums\RuptureMotif;
use App\Mail\InvitationInscription;
use App\Models\Admission;
use App\Models\Promotion;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use App\Scolarite\InscriptionService;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

/**
 * Actions de workflow de l'admission officielle, pilotées par la machine à
 * états (À vérifier → Validé / Rupture). Réutilisées dans la table et le
 * formulaire d'édition.
 */
class AdmissionActions
{
    /** Valider l'admission (contrôle du dossier terminé). */
    public static function valider(): Action
    {
        return Action::make('valider')
            ->label('Valider l\'admission')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Confirmer l\'admission officielle de cet apprenant ? Le dossier a été contrôlé.')
            ->visible(fn (Admission $record) => $record->canTransitionTo(AdmissionStatut::Valide))
            ->action(function (Admission $record) {
                try {
                    $record->transitionTo(AdmissionStatut::Valide);

                    Notification::make()->success()->title('Admission validée')->send();
                } catch (InvalidTransitionException $e) {
                    Notification::make()->danger()->title('Validation refusée')->body($e->getMessage())->send();
                }
            });
    }

    /**
     * Affecter l'apprenant (admission validée) à une classe (cohorte de sa
     * formation) et lui envoyer le formulaire de choix des matières par email.
     * L'apprenant est rattaché à la scolarité dès l'affectation ; ses matières
     * sont enregistrées à sa réponse. Relançable (renvoi de l'invitation).
     */
    public static function affecterClasse(): Action
    {
        return Action::make('affecterClasse')
            ->label('Affecter à une classe & inviter')
            ->icon(Heroicon::OutlinedAcademicCap)
            ->color('primary')
            ->visible(fn (Admission $record) => $record->statut === AdmissionStatut::Valide)
            ->modalHeading('Affecter à une classe et inviter à choisir ses matières')
            ->modalDescription('L\'apprenant sera rattaché à la classe choisie (scolarité) et recevra '
                .'un lien personnel pour sélectionner ses matières au sein du programme de la formation.')
            ->schema([
                Select::make('promotion_id')
                    ->label('Classe (cohorte de la formation)')
                    ->options(fn (Admission $record) => Promotion::query()
                        ->when(
                            $record->candidate?->formation_visee_id,
                            fn ($q, $id) => $q->where('formation_id', $id),
                        )
                        ->with('formation')
                        ->get()
                        ->mapWithKeys(fn (Promotion $p) => [$p->id => $p->nom_complet])
                        ->all())
                    ->searchable()
                    ->required()
                    ->helperText(fn (Admission $record) => $record->candidate?->formation_visee_id
                        ? 'Seules les classes de la formation visée du candidat sont proposées.'
                        : 'Le candidat n\'a pas de formation visée : toutes les classes sont proposées.'),
            ])
            ->action(function (Admission $record, array $data) {
                $candidate = $record->candidate;
                $promotion = Promotion::find($data['promotion_id']);

                if ($candidate === null || $promotion === null) {
                    Notification::make()->danger()->title('Affectation impossible')
                        ->body('Candidat ou classe introuvable.')->send();

                    return;
                }

                $inscription = app(InscriptionService::class)->inviter($candidate, $promotion);
                $lien = route('inscription.matieres', ['token' => $inscription->invitation_token]);

                // Envoi de l'email si l'apprenant a une adresse (driver « log » en dev).
                $envoye = false;

                if (filled($candidate->email)) {
                    Mail::to($candidate->email)->send(new InvitationInscription($inscription, $lien));
                    $envoye = true;
                }

                Notification::make()
                    ->success()
                    ->title('Apprenant affecté à '.$promotion->nom_complet)
                    ->body($envoye
                        ? 'Invitation envoyée à '.$candidate->email.'. Lien : '.$lien
                        : 'Aucun email renseigné — transmettez ce lien manuellement : '.$lien)
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Déclarer la rupture de l'apprenant : l'admission passe en « Rupture »
     * et le dossier Rupture (livrables) est ouvert automatiquement — un seul
     * par contrat.
     */
    public static function declarerRupture(): Action
    {
        return Action::make('declarerRupture')
            ->label('Déclarer une rupture')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Admission $record) => $record->contract_id !== null
                && $record->canTransitionTo(AdmissionStatut::Rupture))
            ->modalHeading('Déclarer la rupture du contrat')
            ->modalDescription('L\'admission passera en « Rupture », le contrat en « Rompu », et un dossier '
                .'sera ouvert dans la section Rupture pour générer les livrables.')
            ->schema([
                DatePicker::make('date_rupture')
                    ->label('Date de rupture')
                    ->default(now())
                    ->displayFormat('d/m/Y')
                    ->required(),
                Select::make('motif')
                    ->label('Motif')
                    ->options(RuptureMotif::class)
                    ->required(),
                Select::make('initiative')
                    ->label('À l\'initiative de')
                    ->options([
                        'Employeur' => 'Employeur',
                        'Apprenti' => 'Apprenti',
                        'Commun accord' => 'Commun accord',
                    ]),
                Textarea::make('commentaire')
                    ->label('Commentaire interne (optionnel)')
                    ->rows(2),
            ])
            ->action(function (Admission $record, array $data) {
                try {
                    app(CycleApprenant::class)->ouvrirRupture($record, [
                        'date_rupture' => $data['date_rupture'] ?? null,
                        'motif' => $data['motif'] ?? null,
                        'initiative' => $data['initiative'] ?? null,
                        'commentaire' => $data['commentaire'] ?? null,
                    ]);

                    Notification::make()
                        ->warning()
                        ->title('Rupture déclarée')
                        ->body('Le dossier de rupture a été ouvert : générez les livrables depuis la section Rupture.')
                        ->send();
                } catch (CycleBloqueException $e) {
                    Notification::make()->danger()->title('Rupture impossible')->body($e->getMessage())->send();
                }
            });
    }
}
