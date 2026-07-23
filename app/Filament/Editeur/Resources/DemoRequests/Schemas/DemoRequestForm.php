<?php

namespace App\Filament\Editeur\Resources\DemoRequests\Schemas;

use App\Enums\DemoRequestStatut;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Écran de traitement d'une demande : les informations du prospect sont en
 * lecture seule (elles viennent de lui), seuls le statut et les notes internes
 * sont modifiables.
 */
class DemoRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Demande du prospect')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->description('Informations transmises via le site vitrine — non modifiables.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('organization_name')->label('Établissement'),
                        TextEntry::make('created_at')->label('Reçue le')->dateTime('d/m/Y à H:i'),
                        TextEntry::make('contact')
                            ->label('Contact')
                            ->state(fn ($record): string => $record->nomComplet()
                                .($record->job_title ? ' — '.$record->job_title : '')),
                        TextEntry::make('email')->label('Adresse e-mail')->copyable(),
                        TextEntry::make('phone')->label('Téléphone')->placeholder('Non renseigné'),
                        TextEntry::make('learner_count')->label('Nombre d\'apprenants')->placeholder('Non renseigné'),
                        TextEntry::make('main_need')->label('Besoin principal')->placeholder('Non renseigné'),
                        TextEntry::make('consent_at')
                            ->label('Consentement recueilli le')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        TextEntry::make('message')
                            ->label('Message')
                            ->placeholder('Aucun message')
                            ->columnSpanFull(),
                    ]),

                Section::make('Suivi commercial')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(DemoRequestStatut::class)
                            ->default(DemoRequestStatut::Nouveau->value)
                            ->required(),
                        Textarea::make('notes_internes')
                            ->label('Notes internes')
                            ->placeholder('Compte rendu d\'échange, date de démonstration prévue…')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
