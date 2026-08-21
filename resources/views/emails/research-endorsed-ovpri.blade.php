@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        A research has been endorsed by the Dean and is now pending your review. College: {{ $research->motherCollege->name ?? 'N/A' }}. Faculty: {{ $research->primaryAuthor->name ?? 'N/A' }}.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">Review Research</a>
    </div>
@endsection
