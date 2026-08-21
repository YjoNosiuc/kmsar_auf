@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        A research from your college has been approved by OVPRI. Faculty: {{ $research->primaryAuthor->name ?? 'N/A' }}.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">View Research</a>
    </div>
@endsection
