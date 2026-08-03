<?php

namespace App\Filament\Pages;

use App\Enums\PresenceStatut;
use App\Filament\Actions\FicheEmargementPdfAction;
use App\Filament\Actions\SignaturesEnLigneAction;
use App\Filament\Resources\Seances\SeanceResource;
use App\Models\Seance;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Cockpit d'émargement d'une séance : un écran unique et vivant pour le formateur.
 * Progression des signatures en temps réel (polling), cartes apprenants avec
 * statut modifiable en un clic, et accès direct à la fiche PDF / aux liens+QR.
 *
 * Non listée au menu : on l'ouvre depuis une séance (« Émargement en direct »).
 */
class CockpitSeance extends Page
{
    protected string $view = 'filament.pages.cockpit-seance';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?string $title = 'Émargement en direct';

    public int $seanceId = 0;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    /** L'id de séance vient de l'URL (?seance=…) ou de l'argument de montage (tests). */
    public function mount(?int $seance = null): void
    {
        $this->seanceId = $seance ?? (int) request('seance');

        abort_unless(Seance::query()->whereKey($this->seanceId)->exists(), 404);
    }

    public function getTitle(): string
    {
        $s = $this->seance();

        return trim(($s->libelle ? $s->libelle.' — ' : '').'Séance du '.$s->date->format('d/m/Y'));
    }

    /** La séance courante, avec ses relations d'affichage. */
    public function seance(): Seance
    {
        return Seance::query()
            ->with('promotion.formation', 'formateur')
            ->findOrFail($this->seanceId);
    }

    protected function getHeaderActions(): array
    {
        return [
            FicheEmargementPdfAction::make()->record(fn (): Seance => $this->seance()),
            SignaturesEnLigneAction::make()->record(fn (): Seance => $this->seance()),
            Action::make('retour')
                ->label('Fiche de la séance')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn (): string => SeanceResource::getUrl('edit', ['record' => $this->seanceId])),
        ];
    }

    /**
     * Lignes d'émargement (une par apprenant), triées par nom, prêtes pour la vue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lignes(): array
    {
        return $this->seance()->presences()
            ->with('candidate')
            ->get()
            ->sortBy(fn ($p) => $p->candidate?->nom.' '.$p->candidate?->prenom)
            ->map(fn ($p): array => [
                'id' => $p->id,
                'nom' => $p->candidate?->nom_complet ?? '—',
                'initiales' => $p->candidate?->initiales ?? '?',
                'statut' => $p->statut?->value,
                'label' => $p->statut?->getLabel() ?? 'Non renseigné',
                'couleur' => $p->statut?->getColor() ?? 'gray',
                'signe' => $p->aSigne(),
                'signed_at' => $p->signed_at,
            ])
            ->values()
            ->all();
    }

    /**
     * Compteurs de tête : signatures et taux de présence.
     *
     * @return array{total: int, signes: int, pct_signes: int, taux_presence: ?int}
     */
    public function stats(): array
    {
        $seance = $this->seance();
        $total = $seance->presences()->count();
        $signes = $seance->presences()->whereNotNull('signed_at')->count();

        return [
            'total' => $total,
            'signes' => $signes,
            'pct_signes' => $total > 0 ? (int) round($signes / $total * 100) : 0,
            'taux_presence' => $seance->tauxPresence(),
        ];
    }

    /** Options de statut pour le sélecteur des cartes. */
    public function statuts(): array
    {
        return collect(PresenceStatut::cases())
            ->mapWithKeys(fn (PresenceStatut $s): array => [$s->value => $s->getLabel()])
            ->all();
    }

    /** Change le statut d'une présence en un clic (depuis une carte). */
    public function definirStatut(int $presenceId, string $statut): void
    {
        $presence = $this->seance()->presences()->whereKey($presenceId)->first();

        if ($presence === null || PresenceStatut::tryFrom($statut) === null) {
            return;
        }

        $presence->update(['statut' => $statut]);

        Notification::make()
            ->success()
            ->title('Présence mise à jour')
            ->body(($presence->candidate?->nom_complet ?? 'Apprenti').' → '.PresenceStatut::from($statut)->getLabel())
            ->send();
    }
}
