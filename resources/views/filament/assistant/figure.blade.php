{{--
    Portrait de l'assistant : l'image fournie si elle existe, sinon le dessin
    SVG de secours. Utilisé au même endroit par le bouton flottant, l'en-tête du
    panneau et chaque bulle de réponse.

    @param \App\Models\FaqBot $bot
--}}
@if ($bot->avatarUrl())
    <img src="{{ $bot->avatarUrl() }}" alt="">
@else
    @include('filament.assistant.avatar', ['bot' => $bot])
@endif
