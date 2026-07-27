<?php

namespace App\Filament\RelationManagers;

use App\Enums\InteractionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Timeline des interactions commerciales (P0-03-6), polymorphe et réutilisable.
 * Se branche sur toute ressource exposant une relation « interactions ». Trace
 * les échanges (appel, e-mail, RDV, visite) et la prochaine action datée (relance).
 */
class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Timeline des interactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Canal')
                    ->options(InteractionType::class)
                    ->default(InteractionType::Appel->value)
                    ->required(),
                DatePicker::make('date_interaction')
                    ->label("Date de l'interaction")
                    ->default(now())
                    ->displayFormat('d/m/Y')
                    ->required(),
                Textarea::make('resume')
                    ->label('Résumé de l\'échange')
                    ->rows(3)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('prochaine_action')
                    ->label('Prochaine action (relance)')
                    ->placeholder('Ex. : renvoyer la proposition, rappeler le tuteur…')
                    ->maxLength(255),
                DatePicker::make('prochaine_action_le')
                    ->label('À faire le')
                    ->displayFormat('d/m/Y')
                    ->helperText('Date de relance : l\'entreprise remontera dans « relances à faire ».'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('resume')
            ->columns([
                TextColumn::make('date_interaction')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Canal')
                    ->badge()
                    ->icon(fn (InteractionType $state): string => $state->getIcon()),
                TextColumn::make('resume')
                    ->label('Échange')
                    ->wrap()
                    ->limit(140),
                TextColumn::make('prochaine_action')
                    ->label('Prochaine action')
                    ->placeholder('—')
                    ->description(fn ($record): ?string => $record->prochaine_action_le
                        ? 'Le '.$record->prochaine_action_le->format('d/m/Y')
                        : null)
                    ->color(fn ($record): string => $record->prochaine_action_le
                        && $record->prochaine_action_le->isPast()
                        ? 'danger'
                        : 'gray'),
                TextColumn::make('user.name')
                    ->label('Par')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Canal')
                    ->options(InteractionType::class),
                Filter::make('relance_due')
                    ->label('Relance à faire')
                    ->query(fn (Builder $query): Builder => $query->relanceDue()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Consigner une interaction')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date_interaction', 'desc');
    }
}
