@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        Congratulations! Your research has been approved by OVPRI.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn btn-gold">View Approved Research</a>
    </div>
@endsection
