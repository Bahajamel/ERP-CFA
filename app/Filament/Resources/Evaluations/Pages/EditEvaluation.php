<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Pages\Notes;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Evaluation;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEvaluation extends EditRecord
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->successRedirectUrl($this->cahierUrl()),
        ];
    }

    /** Après enregistrement, on revient au cahier de notes (plus de page « liste »). */
    protected function getRedirectUrl(): string
    {
        return $this->cahierUrl();
    }

    /** Cahier de notes de la classe de la note éditée. */
    private function cahierUrl(): string
    {
        $note = $this->getRecord();

        return Notes::getUrl($note instanceof Evaluation && $note->promotion_id
            ? ['promotion' => $note->promotion_id]
            : []);
    }
}
