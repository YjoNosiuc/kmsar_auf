@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName ?? ($research->primaryAuthor->name ?? 'there') }},</div>
    <div class="message">{{ $body }}</div>

    @include('emails.partials.research-card', ['research' => $research])

    @if(!empty($remarks))
        <div class="remarks">
            <div class="remarks-label">Remarks</div>
            <div class="remarks-text">{{ $remarks }}</div>
        </div>
    @endif

    @if(!empty($actionUrl))
        <div class="btn-wrap">
            <a href="{{ $actionUrl }}" class="btn">{{ $actionLabel ?? 'Open in KMSAR' }}</a>
        </div>
    @endif
@endsection
