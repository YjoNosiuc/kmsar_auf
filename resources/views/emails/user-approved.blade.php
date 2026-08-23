@extends('emails.layout')

@section('content')
    <div class="greeting">Hello {{ $user->name }},</div>
    <div class="message">
        Your account has been approved by the administrator. You can now login to KMSAR.
    </div>

    <div class="btn-wrap">
        <a href="{{ route('login') }}" class="btn">Login to KMSAR</a>
    </div>
@endsection
