<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Filament\Resources\Admissions\AdmissionActions;
use App\Models\Admission;
use App\Models\Contract;
use App\Parcours\CycleApprenant;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AdmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Admission officielle')
                    ->description('Dernière étape du cycle d\'entrée : le candidat est accepté, l\'entreprise '
                        .'trouvée, le contrat signé par les trois parties et le dossier OPCO créé ou transmis. '
                        .'Le statut évolue via les actions de workflow, pas manuellement.')
                    ->columns(1)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Contrat signé (apprenti — entreprise)')
                            // Prérequis du cycle : contrat signé par les trois parties
                            // ET dossier OPCO créé/transmis, sans admission existante.
                            ->relationship(
                                'contract',
                                'id',
                                fn (Builder $query, ?Admission $record): Builder => $query
                                    ->where(fn (Builder $q) => $q
                                        ->whereIn('statut_contrat', [
                                            ContractStatut::Signe->value,
                                            ContractStatut::TransmisOpco->value,
                                            ContractStatut::Actif->value,
                                        ])
                                        ->orWhere('statut_signature', ContractSignatureStatut::Signe->value))
                                    ->whereHas('opcoFile', fn (Builder $q) => $q->whereNotIn('statut', [
                                        OpcoStatut::NonCree->value,
                                        OpcoStatut::APreparer->value,
                                    ]))
                                    ->where(fn (Builder $q) => $q
                                        ->whereDoesntHave('admission')
                                        ->when($record?->contract_id, fn (Builder $qq, $id) => $qq->orWhere('id', $id))),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Contract $record): string => trim(
                                ($record->candidate?->nom_complet ?? 'Contrat #'.$record->id)
                                .($record->company ? ' — '.$record->company->raison_sociale : ''),
                            ))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->helperText('Seuls les contrats signés par les trois parties dont le dossier OPCO '
                                .'est créé ou transmis pour validation sont proposés. L\'admission démarre « À vérifier ».'),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->placeholder('ex : Points à vérifier avant validation…')
                            ->rows(3),
                    ]),

                // Alerte : le rejet / la correction OPCO ne supprime jamais
                // l'admission, mais le dossier doit être traité.
                Section::make('Action requise sur le dossier OPCO')
                    ->visibleOn('edit')
                    ->visible(fn (?Admission $record): bool => in_array(
                        $record?->contract?->opcoFile?->statut,
                        [OpcoStatut::Rejete, OpcoStatut::EnCorrection],
                        true,
                    ))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->schema([
                        Placeholder::make('alerte_opco')
                            ->hiddenLabel()
                            ->content(fn (?Admission $record): HtmlString => new HtmlString(
                                '<span class="text-danger-600 font-medium">Le dossier OPCO est « '
                                .e($record?->contract?->opcoFile?->statut?->getLabel() ?? '')
                                .' » : une action est nécessaire côté OPCO.</span>'
                                .($record?->contract?->opcoFile?->motif_rejet
                                    ? '<br><span class="text-sm text-gray-500">Motif : '
                                        .e($record->contract->opcoFile->motif_rejet).'</span>'
                                    : '')
                                .'<br><span class="text-sm text-gray-500">L\'admission reste ouverte : '
                                .'corrigez et redéposez le dossier dans le module OPCO.</span>'
                            )),
                    ]),

                // Vision claire du chemin déjà accompli (cycle apprenant).
                Section::make('Parcours de l\'apprenant')
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('parcours')
                            ->hiddenLabel()
                            ->content(fn (?Admission $record): HtmlString|string => $record?->candidate
                                ? new HtmlString(view('filament.parcours.timeline', [
                                    'etapes' => app(CycleApprenant::class)->etapes($record->candidate),
                                ])->render())
                                : '—'),
                    ]),

                // Récapitulatif du dossier : apprenti, entreprise, contrat, OPCO.
                Section::make('Dossier')
                    ->visibleOn('edit')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('identite')
                            ->label('Apprenti')
                            ->content(fn (?Admission $record) => $record?->candidate?->nom_complet ?? '—'),
                        Placeholder::make('contact')
                            ->label('Contact')
                            ->content(fn (?Admission $record) => trim(implode(' · ', array_filter([
                                $record?->candidate?->email,
                                $record?->candidate?->telephone,
                            ]))) ?: '—'),
                        Placeholder::make('entreprise')
                            ->label('Entreprise')
                            ->content(fn (?Admission $record) => $record?->contract?->company?->raison_sociale ?? '—'),
                        Placeholder::make('tuteur')
                            ->label('Tuteur en entreprise')
                            ->content(fn (?Admission $record) => $record?->contract?->tuteur?->nom_complet ?? '—'),
                        Placeholder::make('formation')
                            ->label('Formation')
                            ->content(fn (?Admission $record) => $record?->contract?->formation?->libelle
                                ?? $record?->candidate?->formationVisee?->libelle ?? '—'),
                        Placeholder::make('contrat')
                            ->label('Contrat')
                            ->content(fn (?Admission $record) => $record?->contract
                                ? $record->contract->statut_contrat->getLabel()
                                    .' · signature : '.$record->contract->statut_signature->getLabel()
                                : '—'),
                        Placeholder::make('opco')
                            ->label('Dossier OPCO')
                            ->content(fn (?Admission $record) => $record?->contract?->opcoFile?->statut?->getLabel() ?? '—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pièces du candidat')
                    ->description('Les pièces fournies dans la fiche candidat sont réutilisées ici — '
                        .'aucun nouvel upload n\'est nécessaire.')
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
                                        .'pour pouvoir valider cette admission.</span>'
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

                // Actions de workflow en bas du dossier.
                Actions::make([
                    AdmissionActions::valider(),
                    AdmissionActions::declarerRupture(),
                ])
                    ->visibleOn('edit'),
            ]);
    }
}
