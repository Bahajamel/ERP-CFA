@php
    use App\Models\Organisation;

    /** @var \App\Models\Organisation|null $tenant */
    $tenant = \Filament\Facades\Filament::getTenant();

    // N'afficher que pour un CFA effectivement en période d'essai.
    $afficher = $tenant instanceof Organisation && $tenant->estEnEssai();
    $restants = $afficher ? $tenant->joursEssaiRestants() : null;
@endphp

@if ($afficher)
    @php
        // Urgence croissante à l'approche du terme (palette « cockpit » du panel).
        $palette = match (true) {
            $restants < 0 => ['bg' => 'rgba(244,63,94,.12)', 'bord' => 'rgba(244,63,94,.45)', 'texte' => '#fda4af'],
            $restants <= 3 => ['bg' => 'rgba(244,63,94,.10)', 'bord' => 'rgba(244,63,94,.40)', 'texte' => '#fda4af'],
            $restants <= 7 => ['bg' => 'rgba(245,158,11,.12)', 'bord' => 'rgba(245,158,11,.40)', 'texte' => '#fcd34d'],
            default => ['bg' => 'rgba(6,182,212,.10)', 'bord' => 'rgba(6,182,212,.35)', 'texte' => '#67e8f9'],
        };

        $date = $tenant->date_fin_essai->format('d/m/Y');
        $message = match (true) {
            $restants < 0 => 'Votre essai gratuit a expiré le '.$date.'.',
            $restants === 0 => 'Dernier jour de votre essai gratuit (échéance aujourd\'hui).',
            $restants === 1 => 'Il reste 1 jour à votre essai gratuit (jusqu\'au '.$date.').',
            default => 'Il reste '.$restants.' jours à votre essai gratuit (jusqu\'au '.$date.').',
        };
    @endphp

    <div style="margin:0 0 1rem;padding:.7rem 1rem;border:1px solid {{ $palette['bord'] }};
                background:{{ $palette['bg'] }};border-radius:.6rem;display:flex;
                align-items:center;gap:.6rem;font-size:.875rem;line-height:1.35;color:{{ $palette['texte'] }};">
        <span style="font-size:1.1rem;flex-shrink:0;">🎁</span>
        <span>
            <strong>{{ $message }}</strong>
            Pour conserver votre espace et vos données, contactez-nous pour activer votre abonnement.
        </span>
    </div>
@endif