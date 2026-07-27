<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentType;
use App\Enums\OpcoStatut;
use App\Filament\Resources\Admissions\AdmissionActions;
use App\Models\Admission;
use App\Models\Contract;
use App\Models\Document;
use App\Parcours\CycleApprenant;
use App\Support\SecureMedia;
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
                    ->description('Dernière étape du cycle : le dossier OPCO a été accepté, il ne reste qu\'à '
                        .'valider l\'admission pour inscrire officiellement l\'apprenant au CFA (avec son contrat '
                        .'et sa convention). Le statut évolue via les actions de workflow, pas manuellement.')
                    ->columns(1)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Contrat signé (apprenti — entreprise)')
                            // Prérequis du cycle : contrat signé par les trois parties
                            // ET dossier OPCO accepté, sans admission existante.
                            ->relationship(
                                'contract',
                                'id',
                                fn (Builder $query, ?Admission $record): Builder => $query
                                    ->where(fn (Builder $q) => $q
                                        ->whereIn('statut_contrat', ContractStatut::signes())
                                        ->orWhere('statut_signature', ContractSignatureStatut::Signe->value))
                                    ->whereHas('opcoFile', fn (Builder $q) => $q->whereIn('statut', [
                                        OpcoStatut::Accepte->value,
                                        OpcoStatut::Cloture->value,
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
                            ->helperText('Seuls les contrats signés dont le dossier OPCO est accepté sont proposés. '
                                .'L\'admission démarre « À vérifier ».'),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->placeholder('ex : Points à vérifier avant validation…')
                            ->rows(3),
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

                // Contrat + convention : les pièces qui font foi de l'inscription.
                Section::make('Documents contractuels')
                    ->description('Le contrat (CERFA) et la convention de formation qui accompagnent l\'inscription '
                        .'officielle de l\'apprenant. Générés dans la section Contrats.')
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('documents_contractuels')
                            ->hiddenLabel()
                            ->content(fn (?Admission $record): HtmlString => new HtmlString(
                                self::rendreDocumentsContractuels($record?->contract)
                            )),
                    ]),

                // Actions de workflow en bas du dossier.
                Actions::make([
                    AdmissionActions::valider(),
                    AdmissionActions::affecterClasse(),
                    AdmissionActions::declarerRupture(),
                ])
                    ->visibleOn('edit'),
            ]);
    }

    /**
     * Rend la liste des documents contractuels (CERFA/contrat + convention)
     * du contrat, avec lien de consultation. Renvoie un message d'aiguillage
     * vers la section Contrats pour ceux qui manquent encore.
     */
    private static function rendreDocumentsContractuels(?Contract $contract): string
    {
        if ($contract === null) {
            return '<span class="text-sm text-gray-500">—</span>';
        }

        $attendus = [
            DocumentType::Cerfa->value => 'Contrat (CERFA)',
            DocumentType::Convention->value => 'Convention de formation',
        ];

        $documents = $contract->documents()
            ->whereIn('type', array_keys($attendus))
            ->latest()
            ->get()
            ->keyBy(fn (Document $d): string => $d->type->value);

        $lignes = [];

        foreach ($attendus as $type => $libelle) {
            $doc = $documents->get($type);
            $url = SecureMedia::url($doc?->getFirstMedia('fichier'));

            if ($doc !== null && $url !== null) {
                $lignes[] = '<div class="flex items-center gap-2">'
                    .'<span class="font-medium">📄 '.e($libelle).'</span>'
                    .'<a href="'.e($url).'" target="_blank" rel="noopener" '
                    .'class="text-primary-600 hover:underline text-sm font-medium">Consulter</a>'
                    .'</div>';
            } else {
                $lignes[] = '<div class="flex items-center gap-2 text-gray-500">'
                    .'<span>⏳ '.e($libelle).'</span>'
                    .'<span class="text-sm">à générer dans la section Contrats</span>'
                    .'</div>';
            }
        }

        return '<div class="flex flex-col gap-1">'.implode('', $lignes).'</div>';
    }
}
