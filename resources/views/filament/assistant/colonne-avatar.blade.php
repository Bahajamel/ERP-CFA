{{--
    Portrait de l'assistant dans la liste d'administration.

    On réutilise la vue « figure » du chat plutôt qu'une ImageColumn : celle-ci
    résout ses URL via le disque de stockage, alors que les avatars livrés avec
    le projet vivent dans public/. Passer par « figure » garantit en prime que
    l'administrateur voit exactement ce que voit l'utilisateur — y compris le
    dessin de secours quand aucune image n'est fournie.
--}}
{{-- Utilisable en colonne (le record vient de la table) comme en aperçu de
     formulaire (le bot est passé explicitement). --}}
@php $bot = $bot ?? $getRecord(); @endphp

<span style="display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:9999px;
             overflow:hidden;background:#fff;flex:none;
             box-shadow:0 0 0 2px {{ $bot->color }};">
    @include('filament.assistant.figure', ['bot' => $bot])
</span>
