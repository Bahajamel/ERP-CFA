<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Promotions\Schemas\PromotionWizard;
use App\Models\Promotion;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Validation\ValidationException;

/**
 * Création d'une promotion (session de formation) via un assistant en 3 étapes
 * (Promotion → Informations → Confirmation).
 *
 * La promotion n'est créée qu'à la soumission finale (bouton « Créer la
 * promotion » de l'étape 3). Une même formation peut avoir plusieurs promotions
 * (une par mois, façon intake) — seule l'homonymie exacte est refusée. Les
 * apprenants ne sont pas composés ici : ils se rattachent ensuite sur la fiche.
 */
class CreatePromotion extends CreateRecord
{
    use HasWizard;

    protected static string $resource = PromotionResource::class;

    public function getSteps(): array
    {
        return PromotionWizard::steps();
    }

    protected function getSubmitFormAction(): \Filament\Actions\Action
    {
        return parent::getSubmitFormAction()->label('Créer la promotion');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Anti-doublon : deux promotions de MÊME NOM pour la même formation
        // seraient indistinguables. (Plusieurs promotions par formation restent
        // permises — c'est le principe des sessions mensuelles.)
        $doublon = Promotion::query()
            ->where('formation_id', $data['formation_id'] ?? null)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower(trim((string) ($data['nom'] ?? '')))])
            ->exists();

        if ($doublon) {
            throw ValidationException::withMessages([
                'data.nom' => 'Une promotion portant ce nom existe déjà pour cette formation.',
            ]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Promotion créée avec succès')
            ->body('Vous pouvez maintenant y rattacher les apprenants et créer les contrats — '
                .'ils hériteront de la configuration de cette promotion.');
    }
}
