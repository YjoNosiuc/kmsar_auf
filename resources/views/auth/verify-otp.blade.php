@extends('layouts.auth')

@section('title', 'Verify code — ' . config('app.name', 'KMSAR'))

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
            <p class="kmsar-login-brand-sub">Enter the 6-digit code we sent to your email to continue resetting your password.</p>
        </div>
    </div>

    <div class="kmsar-login-panel">
        <div class="kmsar-login-card">
            <h1 class="kmsar-login-heading">Enter verification code</h1>
            <p class="kmsar-login-lead">Code sent to <strong>{{ $email }}</strong></p>

            @if (session('status'))
                <x-alert type="success" class="kmsar-form-group" :message="session('status')" />
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

            <form method="POST" action="{{ route('password.verify.submit') }}" id="otp-form">
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
                        Verify code
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('password.email') }}" style="margin-top: 1rem; text-align: center;">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="kmsar-btn kmsar-btn--ghost" style="font-size: 0.875rem;">
                    Resend code
                </button>
            </form>

            <p class="kmsar-login-lead" style="margin-top: 1.25rem; margin-bottom: 0; text-align: center;">
                <a href="{{ route('password.request') }}" style="color: #1E3A8A; font-weight: 600; text-decoration: underline;">Use a different email</a>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('otp-form');
    if (!form) {
        return;
    }

    const inputs = Array.from(document.querySelectorAll('.otp-input'));
    const submitBtn = document.getElementById('otp-submit-btn');
    const combinedInput = document.getElementById('otp-combined');
    const countdownEl = document.getElementById('otp-countdown');
    const expiredMsg = document.getElementById('otp-expired-msg');
    let submitting = false;
    let timerInterval = null;

    function syncCombined() {
        if (combinedInput) {
            combinedInput.value = inputs.map(function(i) { return i.value; }).join('');
        }
    }

    function focusInput(index) {
        if (index >= 0 && index < inputs.length) {
            inputs[index].focus();
            inputs[index].select();
        }
    }

    function fillFromDigits(startIndex, digits) {
        digits.forEach(function(digit, offset) {
            const target = inputs[startIndex + offset];
            if (target) {
                target.value = digit;
            }
        });
        syncCombined();

        const nextEmptyIndex = inputs.findIndex(function(i) { return i.value === ''; });
        if (nextEmptyIndex !== -1) {
            focusInput(nextEmptyIndex);
            return;
        }

        focusInput(inputs.length - 1);
        submitOtp();
    }

    function submitOtp() {
        if (submitting) {
            return;
        }
        syncCombined();
        if (!combinedInput || combinedInput.value.length !== 6) {
            return;
        }
        submitting = true;
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        form.submit();
    }

    inputs.forEach(function(input, index) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                e.preventDefault();
                if (input.value === '' && index > 0) {
                    inputs[index - 1].value = '';
                    focusInput(index - 1);
                } else {
                    input.value = '';
                }
                syncCombined();
                return;
            }

            if (e.key === 'ArrowLeft' && index > 0) {
                e.preventDefault();
                focusInput(index - 1);
                return;
            }

            if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                e.preventDefault();
                focusInput(index + 1);
                return;
            }

            if (e.key.length === 1 && !/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        });

        input.addEventListener('input', function() {
            const digits = input.value.replace(/[^0-9]/g, '');
            if (digits.length === 0) {
                input.value = '';
                syncCombined();
                return;
            }

            if (digits.length === 1) {
                input.value = digits;
                syncCombined();
                if (index < inputs.length - 1) {
                    focusInput(index + 1);
                } else if (inputs.every(function(i) { return i.value !== ''; })) {
                    submitOtp();
                }
                return;
            }

            fillFromDigits(index, digits.split('').slice(0, inputs.length - index));
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData)
                .getData('text').replace(/[^0-9]/g, '');
            if (paste.length > 0) {
                fillFromDigits(0, paste.slice(0, 6).split(''));
            }
        });

        input.addEventListener('click', function() {
            const firstEmpty = inputs.find(function(i) { return i.value === ''; });
            focusInput(firstEmpty ? inputs.indexOf(firstEmpty) : index);
        });
    });

    form.addEventListener('submit', function() {
        syncCombined();
        if (!submitting) {
            submitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        }
    });

    let seconds = 60;
    function renderCountdown() {
        if (!countdownEl) {
            return;
        }
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
        countdownEl.style.color = seconds <= 10 ? '#DC2626' : '#64748B';
    }

    renderCountdown();
    timerInterval = setInterval(function() {
        seconds--;
        renderCountdown();
        if (seconds <= 0) {
            clearInterval(timerInterval);
            if (countdownEl) {
                countdownEl.textContent = 'Expired';
            }
            inputs.forEach(function(i) { i.disabled = true; });
            if (expiredMsg) {
                expiredMsg.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        }
    }, 1000);

    if (inputs.length > 0) {
        focusInput(0);
    }
});
</script>
@endpush
