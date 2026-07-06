<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatut;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Les tâches à traiter de l'utilisateur connecté, triées par urgence, avec une
 * action « Terminer » en un clic. Chaque utilisateur pilote sa charge de travail
 * depuis le tableau de bord.
 */
class MesTachesTable extends BaseWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Mes tâches à traiter';

    /** Libellés français des objets rattachés (par type). */
    private const OBJETS = [
        'Candidate' => 'Candidat',
        'Contract' => 'Contrat',
        'OpcoFile' => 'Dossier OPCO',
        'OpcoPayment' => 'Versement OPCO',
        'Admission' => 'Admission',
        'Invoice' => 'Facture',
        'Company' => 'Entreprise',
        'QualiopiIndicator' => 'Qualiopi',
    ];

    public static function canView(): bool
    {
        return Auth::check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()
                    ->where('assignee_id', Auth::id())
                    ->whereIn('statut', [
                        TaskStatut::AFaire->value,
                        TaskStatut::EnCours->value,
                        TaskStatut::EnAttente->value,
                        TaskStatut::EnRetard->value,
                    ])
                    ->orderByRaw("CASE priorite WHEN 'urgente' THEN 0 WHEN 'haute' THEN 1 WHEN 'normale' THEN 2 ELSE 3 END")
                    ->orderBy('due_date')
            )
            ->emptyStateHeading('Rien à traiter — tout est à jour 🎉')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('titre')
                    ->label('Tâche')
                    ->description(fn (Task $record): ?string => $record->description)
                    ->wrap(),
                TextColumn::make('taskable_type')
                    ->label('Rattachée à')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (self::OBJETS[class_basename($state)] ?? class_basename($state))
                        : '—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (Task $record): string => $record->due_date && $record->due_date->isPast() ? 'danger' : 'gray'),
                TextColumn::make('priorite')
                    ->label('Priorité')
                    ->badge(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('terminer')
                    ->label('Terminer')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (Task $record): void {
                        $record->update(['statut' => TaskStatut::Terminee->value]);

                        Notification::make()
                            ->success()
                            ->title('Tâche terminée')
                            ->body(Str::limit($record->titre, 60))
                            ->send();
                    }),
            ])
            ->paginated([5, 10, 25]);
    }
}
