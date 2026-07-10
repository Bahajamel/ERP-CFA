<?php

namespace App\Filament\Resources\Evaluations;

use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Filament\Resources\Evaluations\Pages\EditEvaluation;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Filament\Resources\Evaluations\Schemas\EvaluationForm;
use App\Filament\Resources\Evaluations\Tables\EvaluationsTable;
use App\Models\Evaluation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
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

    // Le menu « Notes » est la page cahier de notes (Pages\Notes) ; la ressource
    // ne sert qu'à créer/éditer une note individuelle (routes conservées).
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'note';

    protected static ?string $pluralModelLabel = 'notes';

    public static function form(Schema $schema): Schema
    {
        return EvaluationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EvaluationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluations::route('/'),
            'create' => CreateEvaluation::route('/create'),
            'edit' => EditEvaluation::route('/{record}/edit'),
        ];
    }
}
