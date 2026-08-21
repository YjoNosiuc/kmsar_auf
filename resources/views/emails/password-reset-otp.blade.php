@extends('emails.layout')

@section('content')
    <div class="greeting">Password Reset Request</div>
    <div class="message">
        You requested to reset your KMSAR password. Use the verification code below:
    </div>

    <div style="text-align:center; margin:28px 0;">
        <div class="otp-code">{{ $otp }}</div>
        <div style="font-size:12px; color:#64748B; margin-top:10px;">
            This code expires in <strong>1 minute</strong>
        </div>
    </div>

    <div class="message" style="text-align:center; color:#94A3B8; font-size:12px; margin-bottom:0;">
        If you did not request a password reset, please ignore this email.
        Your account remains secure.
    </div>
@endsection
