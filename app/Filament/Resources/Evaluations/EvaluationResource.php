<?php

namespace App\Filament\Resources\Evaluations;

use App\Filament\Pages\Notes;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Filament\Resources\Evaluations\Schemas\EvaluationForm;
use App\Models\Evaluation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EvaluationResource extends Resource
{
    protected static ?string $model = Evaluation::class;

    /** Même périmètre que la scolarité (Séances / Assiduité). */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    // Le cahier de notes (Pages\Notes) remplace toute gestion « en liste » des
    // évaluations : cette ressource ne conserve QUE la route d'édition d'une note
    // individuelle (ouverte depuis une pastille du cahier). Ni liste, ni création.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'note';

    protected static ?string $pluralModelLabel = 'notes';

    public static function form(Schema $schema): Schema
    {
        return EvaluationForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            // Uniquement l'édition d'une note (depuis le cahier de notes) :
            // plus aucune page « liste » ni « création » sur /admin/evaluations.
            'edit' => EditEvaluation::route('/{record}/edit'),
        ];
    }

    /**
     * La ressource n'a plus de page « liste » : son URL d'index (fil d'Ariane,
     * lien retour…) pointe vers le cahier de notes, qui la remplace entièrement.
     */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return Notes::getUrl(panel: $panel, isAbsolute: $isAbsolute);
    }
}
