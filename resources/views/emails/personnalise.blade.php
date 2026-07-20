@component('mail::message')
{!! nl2br(e($corps)) !!}

@isset($expediteur)
Cordialement,<br>
{{ $expediteur }}
@endisset
@endcomponent
