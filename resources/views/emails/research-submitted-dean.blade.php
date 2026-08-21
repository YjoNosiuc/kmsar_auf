@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $recipientName }},</div>
    <div class="message">
        A new research submission requires your endorsement. Faculty: {{ $research->primaryAuthor->name ?? 'N/A' }}. College: {{ $research->motherCollege->name ?? 'N/A' }}.
    </div>

    @include('emails.partials.research-card', ['research' => $research])

    <div class="btn-wrap">
        <a href="{{ $actionUrl }}" class="btn">Review Research</a>
    </div>
@endsection
