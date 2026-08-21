@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        Your research has been returned by OVPRI for revision.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    @if(!empty($remarks))
        <div class="remarks">
            <div class="remarks-label">Remarks</div>
            <div class="remarks-text">{{ $remarks }}</div>
        </div>
    @endif

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">Revise Research</a>
    </div>
@endsection
