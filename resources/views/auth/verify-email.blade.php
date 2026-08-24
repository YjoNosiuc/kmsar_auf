@extends('layouts.auth')

@section('title', 'Verify email — ' . config('app.name', 'KMSAR'))

@push('styles')
<style>
    .kmsar-auth-shell { margin: 0; min-height: 100vh; font-family: var(--font-sans, 'Inter', system-ui, sans-serif); background: var(--color-surface, #F8FAFC); }
    .kmsar-login { display: flex; min-height: 100vh; flex-direction: column; }
    @media (min-width: 768px) { .kmsar-login { flex-direction: row; } }
    .kmsar-login-brand { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 2.5rem 2rem; background-color: #1E3A8A; color: #fff; position: relative; overflow: hidden; }
    .kmsar-login-brand::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 20% 20%, rgba(212, 175, 55, 0.12), transparent 55%); pointer-events: none; }
    .kmsar-login-brand-inner { position: relative; max-width: 28rem; margin: 0 auto; width: 100%; }
    .kmsar-login-brand-inst { font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: rgba(255, 255, 255, 0.85); margin-bottom: 0.5rem; }
    .kmsar-login-brand-title { font-size: clamp(1.75rem, 4vw, 2.25rem); font-weight: 700; line-height: 1.15; margin-bottom: 0.75rem; }
    .kmsar-login-brand-title span { color: #D4AF37; }
    .kmsar-login-brand-sub { font-size: 0.9375rem; line-height: 1.55; color: rgba(255, 255, 255, 0.88); }
    .kmsar-login-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem 3rem; background: var(--color-card, #fff); }
    @media (min-width: 768px) { .kmsar-login-panel { box-shadow: -12px 0 40px rgba(15, 23, 42, 0.06); } }
    .kmsar-login-card { width: 100%; max-width: 24rem; }
    .kmsar-login-heading { font-size: 1.375rem; font-weight: 700; color: var(--color-text-primary, #0F172A); margin-bottom: 0.25rem; }
    .kmsar-login-lead { font-size: 0.875rem; color: var(--color-text-secondary, #475569); margin-bottom: 1.5rem; }
    .kmsar-otp-row { display: flex; gap: 0.5rem; justify-content: center; margin: 1rem 0 1.25rem; }
    .otp-input {
        width: 2.75rem; height: 3.25rem; text-align: center; font-size: 1.25rem; font-weight: 700;
        border: 1px solid #E2E8F0; border-radius: 0.5rem; color: #0F172A; background: #fff;
    }
    .otp-input:focus { outline: 2px solid #1E3A8A; outline-offset: 1px; border-color: #1E3A8A; }
    .otp-input:disabled { background: #F1F5F9; color: #94A3B8; }
    .kmsar-otp-countdown { font-size: 0.8125rem; color: #64748B; text-align: center; margin-bottom: 1rem; }
    .kmsar-otp-expired { display: none; font-size: 0.875rem; color: #DC2626; text-align: center; margin-bottom: 1rem; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="kmsar-login">
    <div class="kmsar-login-brand">
        <div class="kmsar-login-brand-inner">
            <div class="kmsar-login-brand-inst">Angeles University Foundation</div>
            <div class="kmsar-login-brand-title">KMSAR <span>Verify</span></div>
            <p class="kmsar-login-brand-sub">Enter the 6-digit code we sent to your email to complete your registration.</p>
        </div>
    </div>

    <div class="kmsar-login-panel">
        <div class="kmsar-login-card">
            <h1 class="kmsar-login-heading">Verify your email</h1>
            <p class="kmsar-login-lead">Code sent to <strong>{{ $email }}</strong></p>

            @if (session('success'))
                <x-alert type="success" class="kmsar-form-group" :message="session('success')" />
            @endif

            @if ($errors->any())
                <x-alert type="danger" class="kmsar-form-group">
                    <ul style="margin:0;padding-left:1.1rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <p class="kmsar-otp-countdown">
                Code expires in <span id="otp-countdown">1:00</span>
            </p>
            <p id="otp-expired-msg" class="kmsar-otp-expired">Code expired. Please request a new one.</p>

            <form method="POST" action="{{ route('register.confirm-email') }}" id="otp-form">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="otp" id="otp-combined" value="">

                <div class="kmsar-otp-row">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]"
                            maxlength="1"
                            class="otp-input"
                            autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                            aria-label="Digit {{ $i + 1 }}"
                        >
                    @endfor
                </div>

                <div class="kmsar-form-group" style="margin-top: 0.5rem;">
                    <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--lg" style="width: 100%;" id="otp-submit-btn">
                        Verify email
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('register.resend-verification') }}" style="margin-top: 1rem; text-align: center;">
                @csrf
                <button type="submit" class="kmsar-btn kmsar-btn--ghost" style="font-size: 0.875rem;">
                    Resend code
                </button>
            </form>

            <p class="kmsar-login-lead" style="margin-top: 1.25rem; margin-bottom: 0; text-align: center;">
                <a href="{{ route('register') }}" style="color: #1E3A8A; font-weight: 600; text-decoration: underline;">Use a different email</a>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.otp-input');
    const form = document.getElementById('otp-form');
    const submitBtn = document.getElementById('otp-submit-btn');
    let submitting = false;

    function submitOtp() {
        if (submitting) {
            return;
        }
        const otp = Array.from(inputs).map(function(i) { return i.value; }).join('');
        if (otp.length !== 6) {
            return;
        }
        submitting = true;
        document.getElementById('otp-combined').value = otp;
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        form.submit();
    }

    inputs.forEach(function(input, index) {
        input.addEventListener('keydown', function(e) {
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1) {
                if (e.keyCode === 8) {
                    if (input.value === '' && index > 0) {
                        inputs[index - 1].value = '';
                        inputs[index - 1].focus();
                    } else {
                        input.value = '';
                    }
                    e.preventDefault();
                }
                return;
            }
            if ((e.keyCode < 48 || e.keyCode > 57) &&
                (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
                return;
            }
            input.value = '';
        });

        input.addEventListener('input', function() {
            input.value = input.value.replace(/[^0-9]/g, '');
            if (input.value.length === 1) {
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                } else if (Array.from(inputs).every(function(i) { return i.value !== ''; })) {
                    submitOtp();
                }
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData)
                .getData('text').replace(/[^0-9]/g, '');
            if (paste.length === 6) {
                inputs.forEach(function(inp, i) {
                    inp.value = paste[i] || '';
                });
                submitOtp();
            }
        });

        input.addEventListener('click', function() {
            const firstEmpty = Array.from(inputs).find(function(i) { return i.value === ''; });
            if (firstEmpty) firstEmpty.focus();
            else input.focus();
        });
    });

    form.addEventListener('submit', function(e) {
        if (submitting) {
            e.preventDefault();
            return;
        }
        const otp = Array.from(inputs).map(function(i) { return i.value; }).join('');
        document.getElementById('otp-combined').value = otp;
        submitting = true;
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    });

    let seconds = 60;
    const countdownEl = document.getElementById('otp-countdown');
    const timerInterval = setInterval(function() {
        seconds--;
        if (countdownEl) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
            if (seconds <= 10) countdownEl.style.color = '#DC2626';
        }
        if (seconds <= 0) {
            clearInterval(timerInterval);
            if (countdownEl) countdownEl.textContent = 'Expired';
            inputs.forEach(i => i.disabled = true);
            const expiredMsg = document.getElementById('otp-expired-msg');
            if (expiredMsg) expiredMsg.style.display = 'block';
        }
    }, 1000);

    if (inputs.length > 0) inputs[0].focus();
});
</script>
@endpush
