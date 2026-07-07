<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\DocumentType;
use App\Enums\OpcoStatut;
use App\Enums\PaymentStatut;
use App\Enums\TaskStatut;
use App\Models\Admission;
use App\Models\Contract;
use App\Models\OpcoPayment;
use App\Models\Task;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Cockpit « Priorités du jour » : répond à la question « qu'est-ce qui demande
 * mon attention aujourd'hui ? ». Chaque carte est cliquable vers la liste
 * concernée et n'apparaît que si l'utilisateur a accès au module. Quand tout
 * est traité, une carte « à jour » rassure au lieu d'un espace vide.
 */
class PrioritesDuJourWidget extends Widget
{
    protected string $view = 'filament.widgets.priorites-du-jour';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::check();
    }

    /** @return array<int, array{count:int, label:string, hint:string, tone:string, icon:string, url:?string}> */
    protected function priorites(): array
    {
        $user = Auth::user();
        $items = [];

        if ($user->can('access_admissions')) {
            $sansCv = Admission::query()
                ->where('statut', '!=', AdmissionStatut::Valide->value)
                ->whereHas('candidate', fn ($q) => $q
                    ->whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'cv'))
                    ->whereDoesntHave('documents', fn ($d) => $d
                        ->where('type', DocumentType::CvCandidat->value)
                        ->whereHas('media')))
                ->count();

            if ($sansCv > 0) {
                $items[] = [
                    'count' => $sansCv,
                    'label' => $sansCv > 1 ? 'dossiers sans CV' : 'dossier sans CV',
                    'hint' => 'Ces admissions ne pourront pas être validées sans le CV.',
                    'tone' => 'danger',
                    'icon' => 'document-minus',
                    'url' => route('filament.admin.resources.admissions.index'),
                ];
            }
        }

        if ($user->can('access_contracts')) {
            $aSigner = Contract::query()
                ->where('statut_signature', '!=', ContractSignatureStatut::Signe->value)
                ->count();

            if ($aSigner > 0) {
                $items[] = [
                    'count' => $aSigner,
                    'label' => $aSigner > 1 ? 'contrats à faire signer' : 'contrat à faire signer',
                    'hint' => 'Sans signature, le dossier OPCO ne peut pas être déposé.',
                    'tone' => 'warning',
                    'icon' => 'pencil-square',
                    'url' => route('filament.admin.resources.contracts.index'),
                ];
            }
        }

        if ($user->can('access_opco')) {
            $bloques = \App\Models\OpcoFile::query()
                ->whereIn('statut', [OpcoStatut::Rejete->value, OpcoStatut::EnCorrection->value])
                ->count();

            if ($bloques > 0) {
                $items[] = [
                    'count' => $bloques,
                    'label' => $bloques > 1 ? 'dossiers OPCO bloqués' : 'dossier OPCO bloqué',
                    'hint' => 'Financement en attente : corrigez puis redéposez.',
                    'tone' => 'danger',
                    'icon' => 'exclamation-triangle',
                    'url' => route('filament.admin.resources.opco-files.index'),
                ];
            }

            $echeances = OpcoPayment::query()
                ->where('statut', PaymentStatut::Attendu->value)
                ->whereBetween('date_prevue', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->count();

            if ($echeances > 0) {
                $items[] = [
                    'count' => $echeances,
                    'label' => $echeances > 1 ? 'versements OPCO sous 7 jours' : 'versement OPCO sous 7 jours',
                    'hint' => 'Échéances à surveiller cette semaine.',
                    'tone' => 'info',
                    'icon' => 'banknotes',
                    'url' => route('filament.admin.resources.opco-files.index'),
                ];
            }
        }

        $enRetard = Task::query()
            ->where('assignee_id', $user->id)
            ->where('statut', TaskStatut::EnRetard->value)
            ->count();

        if ($enRetard > 0) {
            $items[] = [
                'count' => $enRetard,
                'label' => $enRetard > 1 ? 'de mes tâches en retard' : 'de mes tâches en retard',
                'hint' => 'À traiter en priorité aujourd\'hui.',
                'tone' => 'danger',
                'icon' => 'clock',
                'url' => $user->can('access_tasks') ? route('filament.admin.resources.tasks.index') : null,
            ];
        }

        return $items;
    }

    protected function getViewData(): array
    {
        return ['priorites' => $this->priorites()];
    }
}
