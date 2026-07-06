<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Filament\Resources\Admissions\AdmissionActions;
use App\Models\Admission;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AdmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Dossier de pré-admission")
                    ->description('Le statut évolue via les actions de workflow, pas manuellement.')
                    ->columns(1)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat')
                            ->relationship('candidate', 'nom')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->required()
                            // Un dossier reste rattaché à son candidat.
                            ->disabledOn('edit'),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->placeholder('ex : Remarques sur le dossier, points à vérifier…')
                            ->rows(3),
                    ]),

                // Récapitulatif du candidat + CV (pré-admission = pas d'autres documents).
                Section::make('Candidat')
                    ->visibleOn('edit')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('identite')
                            ->label('Identité')
                            ->content(fn (?Admission $record) => $record?->candidate?->nom_complet ?? '—'),
                        Placeholder::make('contact')
                            ->label('Contact')
                            ->content(fn (?Admission $record) => trim(implode(' · ', array_filter([
                                $record?->candidate?->email,
                                $record?->candidate?->telephone,
                            ]))) ?: '—'),
                        Placeholder::make('adresse')
                            ->label('Adresse')
                            ->content(fn (?Admission $record) => trim(implode(' ', array_filter([
                                $record?->candidate?->adresse,
                                $record?->candidate?->code_postal,
                                $record?->candidate?->ville,
                            ]))) ?: '—'),
                        Placeholder::make('formation')
                            ->label('Formation visée')
                            ->content(fn (?Admission $record) => $record?->candidate?->formationVisee?->libelle ?? '—'),
                        Placeholder::make('disponibilites')
                            ->label('Disponibilités')
                            ->content(fn (?Admission $record) => $record?->candidate?->availabilities->isNotEmpty()
                                ? $record->candidate->availabilities->map(fn ($a) => $a->libelle())->implode(' · ')
                                : ($record?->candidate?->disponibilite ?: '—'))
                            ->columnSpanFull(),
                    ]),

                Section::make('CV')
                    ->description('Seul document requis à cette étape. Il est fourni depuis la fiche candidat.')
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('cv')
                            ->hiddenLabel()
                            ->content(function (?Admission $record): HtmlString {
                                $candidate = $record?->candidate;

                                if ($candidate !== null && $candidate->hasCv()) {
                                    $url = e($candidate->cvUrl());

                                    return new HtmlString(
                                        '<a href="'.$url.'" target="_blank" rel="noopener" '
                                        .'class="text-primary-600 hover:underline font-medium">📄 Télécharger le CV</a>'
                                    );
                                }

                                return new HtmlString(
                                    '<span class="text-danger-600 font-medium">⚠ CV manquant — '
                                    .'ajoutez-le dans la fiche candidat pour pouvoir valider ce dossier.</span>'
                                );
                            }),
                    ]),

                // Action de validation en bas du dossier, après les infos et le CV.
                Actions::make([
                    AdmissionActions::valider(),
                ])
                    ->visibleOn('edit'),
            ]);
    }
}
