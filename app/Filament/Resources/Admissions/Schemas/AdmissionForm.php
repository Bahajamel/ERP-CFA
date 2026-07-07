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
                Section::make('Dossier de pré-admission')
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
                        Placeholder::make('disponibilite')
                            ->label('Disponible à partir du')
                            ->content(fn (?Admission $record) => $record?->candidate?->date_disponibilite?->format('d/m/Y')
                                ?? ($record?->candidate?->disponibilite ?: '—'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Pièces du candidat')
                    ->description('Les pièces fournies dans la fiche candidat sont réutilisées ici — '
                        .'aucun nouvel upload n\'est nécessaire. Le CV est le seul document requis à cette étape.')
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('cv')
                            ->label('CV')
                            ->content(function (?Admission $record): HtmlString {
                                $info = $record?->candidate?->cvInfo();

                                if ($info === null) {
                                    return new HtmlString(
                                        '<span class="text-danger-600 font-medium">⚠ Aucun CV fourni pour ce candidat.</span>'
                                        .'<br><span class="text-sm text-gray-500">Ajoutez-le dans la fiche candidat '
                                        .'pour pouvoir valider ce dossier.</span>'
                                    );
                                }

                                $nom = e($info['name']);
                                $type = e(strtoupper($info['extension'] ?: 'fichier'));
                                $date = $info['added_at']?->format('d/m/Y') ?? '—';
                                $url = e($info['url']);

                                return new HtmlString(
                                    '<div class="flex flex-col gap-1">'
                                    .'<div class="flex items-center gap-2">'
                                    .'<span class="font-medium">📄 '.$nom.'</span>'
                                    .'<span class="text-xs rounded bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5">'.$type.'</span>'
                                    .'</div>'
                                    .'<span class="text-sm text-gray-500">Ajouté le '.$date.'</span>'
                                    .'<a href="'.$url.'" target="_blank" rel="noopener" '
                                    .'class="text-primary-600 hover:underline font-medium">Consulter / télécharger le CV</a>'
                                    .'</div>'
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
