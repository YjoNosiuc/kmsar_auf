@extends('emails.layout')

@section('content')
    <div class="greeting">Verify Your Email Address</div>
    <div class="message">
        Hello {{ $recipientName }},
    </div>
    <div class="message">
        Please enter this verification code to complete your registration:
    </div>

    <div style="text-align:center; margin:28px 0;">
        <div class="otp-code">{{ $otp }}</div>
        <div style="font-size:12px; color:#64748B; margin-top:10px;">
            This code expires in <strong>1 minute</strong>
        </div>
    </div>

    <div class="message" style="text-align:center; color:#94A3B8; font-size:12px; margin-bottom:0;">
        If you did not register, please ignore this email.
    </div>
@endsection
