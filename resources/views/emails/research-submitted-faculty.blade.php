@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        Your research has been submitted for Dean review. The Dean of {{ $research->motherCollege->name ?? 'your college' }} will review your submission.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">View Research</a>
    </div>
@endsection
