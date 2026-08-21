@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        A research from your college has been rejected. Faculty: {{ $research->primaryAuthor->name ?? 'N/A' }}.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    @if(!empty($remarks))
        <div class="remarks">
            <div class="remarks-label">Remarks</div>
            <div class="remarks-text">{{ $remarks }}</div>
        </div>
    @endif

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">View Research</a>
    </div>
@endsection
