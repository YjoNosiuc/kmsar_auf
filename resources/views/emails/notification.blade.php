@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">{{ $bodyText }}</div>

    @if(!empty($referenceNumber) || !empty($researchTitle))
        <div class="research-card">
            @if(!empty($referenceNumber))
                <div class="ref">{{ $referenceNumber }}</div>
            @endif
            @if(!empty($researchTitle))
                <div class="rtitle">{{ $researchTitle }}</div>
            @endif
        </div>
    @endif

    @if(!empty($remarks))
        <div class="remarks">
            <div class="remarks-label">Remarks</div>
            <div class="remarks-text">{{ $remarks }}</div>
        </div>
    @endif

    @if(!empty($actionUrl))
        <div class="btn-wrap">
            <a href="{{ $actionUrl }}" class="btn">{{ $actionLabel }}</a>
        </div>
    @endif
@endsection
