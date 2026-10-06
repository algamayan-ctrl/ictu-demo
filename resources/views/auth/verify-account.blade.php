@extends('layouts.app', ['title' => 'Verify Account'])

@section('content')

@php
    $otpValue = preg_replace(
        '/\D+/',
        '',
        (string) old('otp', '')
    );

    $otpDigits = str_split(substr($otpValue, 0, 6));

    $verificationEmail = old(
        'email',
        request('email', '')
    );
@endphp

<style>
/* ==========================================================
   SPMU-ACPMP UNIVERSAL OTP AUTH PAGE
   ========================================================== */

/* The normal public header is intentionally hidden here so
   Verification uses the same full-page experience as Login. */
.site-header.public-header {
    display: none !important;
}

/* The app layout normally prints flash messages above the page content.
   The OTP card displays them itself so there is no duplicate. */
#main-content > .notice {
    display: none !important;
}

html,
body {
    min-height: 100%;
}

body {
    margin: 0 !important;
    background: #eef3f9 !important;
}

#main-content {
    width: 100% !important;
    max-width: none !important;
    min-height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    background: transparent !important;
}

body > footer {
    display: none !important;
}

.otp-auth-page {
    position: relative;
    width: 100%;
    min-height: 100vh;
    box-sizing: border-box;

    display: flex;
    align-items: flex-start;
    justify-content: flex-start;

    padding: 48px 40px 56px;

    background:
        linear-gradient(
            90deg,
            rgba(255,255,255,.98) 0%,
            rgba(255,255,255,.96) 20%,
            rgba(255,255,255,.84) 40%,
            rgba(255,255,255,.42) 67%,
            rgba(255,255,255,.10) 100%
        ),
        url('{{ asset('images/cspc-campus-hero.png') }}')
        center right / cover no-repeat;

    overflow-x: hidden;
}

.otp-auth-card {
    width: 100%;
    max-width: 420px;
    box-sizing: border-box;

    padding: 27px 28px 26px;

    background: #ffffff;
    border: 1px solid #d7e2ee;
    border-radius: 17px;

    box-shadow:
        0 18px 48px rgba(33, 64, 98, .10),
        0 3px 10px rgba(33, 64, 98, .035);

    color: #102a43;
}

.otp-auth-brand {
    display: flex;
    justify-content: center;
    margin: 0 0 24px;
}

.otp-auth-brand img {
    display: block;
    width: 278px;
    max-width: 86%;
    height: auto;
}

.otp-auth-heading {
    margin: 0 0 23px;
}

.otp-auth-heading h1 {
    margin: 0 0 7px;

    color: #062951;
    font-size: 24px;
    font-weight: 780;
    line-height: 1.18;
    letter-spacing: -.025em;
}

.otp-auth-heading p {
    max-width: 350px;
    margin: 0;

    color: #667f9a;
    font-size: 12.5px;
    font-weight: 500;
    line-height: 1.52;
}

.otp-card-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;

    margin: 0 0 20px;
    padding: 12px 13px;

    border: 1px solid #a9dfc0;
    border-radius: 9px;

    background: #effaf3;
    color: #087943;

    font-size: 11.5px;
    font-weight: 560;
    line-height: 1.45;
}

.otp-card-notice.is-error {
    border-color: #f0b3ae;
    background: #fff2f1;
    color: #b42318;
}

.otp-card-notice-icon {
    display: grid;
    place-items: center;

    flex: 0 0 22px;
    width: 22px;
    height: 22px;

    margin-top: 1px;

    border-radius: 50%;
    background: #0a8a50;
    color: #ffffff;
}

.otp-card-notice.is-error .otp-card-notice-icon {
    background: #c93c32;
}

.otp-card-notice-icon svg {
    width: 13px;
    height: 13px;
}

.otp-form {
    display: grid;
    gap: 16px;
}

.otp-field {
    display: grid;
    gap: 7px;
    margin: 0;
}

.otp-field-label {
    color: #102c4e;
    font-size: 12px;
    font-weight: 780;
    line-height: 1.25;
}

.otp-email-shell {
    position: relative;
}

.otp-email-icon {
    position: absolute;
    top: 50%;
    left: 13px;

    display: grid;
    place-items: center;

    width: 18px;
    height: 18px;

    color: #7890aa;
    transform: translateY(-50%);
    pointer-events: none;
}

.otp-email-icon svg {
    width: 18px;
    height: 18px;
}

.otp-email-input {
    width: 100%;
    height: 54px;
    box-sizing: border-box;

    padding: 0 14px 0 43px;

    border: 1px solid #bfd0e3;
    border-radius: 9px;

    outline: none;
    background: #ffffff;
    color: #102a43;

    font: inherit;
    font-size: 12.5px;
    font-weight: 620;

    transition:
        border-color .15s ease,
        box-shadow .15s ease,
        background-color .15s ease;
}

.otp-email-input:hover {
    border-color: #a9bdd3;
}

.otp-email-input:focus {
    border-color: #2376ee;
    box-shadow: 0 0 0 3px rgba(35, 118, 238, .11);
}

.otp-digit-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 9px;
}

.otp-digit-input {
    width: 100%;
    min-width: 0;
    height: 58px;
    box-sizing: border-box;

    padding: 0;

    border: 1px solid #bfd0e3;
    border-radius: 9px;

    outline: none;
    background: #ffffff;
    color: #0c2c54;

    text-align: center;
    font-size: 23px;
    font-weight: 820;
    line-height: 1;

    font-variant-numeric: tabular-nums;
    caret-color: #1769e0;

    transition:
        border-color .15s ease,
        box-shadow .15s ease,
        transform .12s ease;
}

.otp-digit-input:hover {
    border-color: #9eb6ce;
}

.otp-digit-input:focus {
    border-color: #1769e0;
    box-shadow: 0 0 0 3px rgba(23, 105, 224, .12);
    transform: translateY(-1px);
}

.otp-field-error {
    margin: -1px 0 0;
    color: #c52a22;
    font-size: 10.5px;
    font-weight: 600;
    line-height: 1.35;
}

.otp-action-stack {
    display: grid;
    gap: 11px;
    margin-top: 2px;
}

.otp-primary-button,
.otp-secondary-button {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;

    width: 100%;
    min-height: 50px;

    padding: 10px 18px;

    border-radius: 9px;

    cursor: pointer;
    font-family: inherit;
    font-size: 12px;
    font-weight: 790;
    line-height: 1;

    transition:
        background-color .15s ease,
        border-color .15s ease,
        box-shadow .15s ease,
        transform .12s ease;
}

.otp-primary-button {
    border: 1px solid #1769e0 !important;
    background:
        linear-gradient(
            135deg,
            #2159d7 0%,
            #2879f4 100%
        ) !important;
    color: #ffffff !important;

    box-shadow: 0 8px 18px rgba(31, 105, 224, .15);
}

.otp-primary-button:hover,
.otp-primary-button:focus-visible {
    border-color: #155fc9 !important;
    background:
        linear-gradient(
            135deg,
            #1b50ca 0%,
            #1f6fe5 100%
        ) !important;

    color: #ffffff !important;
    box-shadow: 0 10px 22px rgba(31, 105, 224, .20);
    transform: translateY(-1px);
}

.otp-secondary-button {
    border: 1px solid #b9cade !important;
    background: #ffffff !important;
    color: #173655 !important;

    box-shadow: none !important;
}

.otp-secondary-button:hover,
.otp-secondary-button:focus-visible {
    border-color: #8eacca !important;
    background: #f7fbff !important;
    color: #1769e0 !important;
}

.otp-help {
    display: grid;
    gap: 3px;

    margin: 15px 0 0;

    color: #7187a0;
    font-size: 10.5px;
    font-weight: 500;
    line-height: 1.42;
}

.otp-back {
    display: flex;
    justify-content: center;

    margin-top: 22px;
}

.otp-back a {
    color: #0969e8;
    font-size: 11.5px;
    font-weight: 760;
    text-decoration: none;
}

.otp-back a:hover,
.otp-back a:focus-visible {
    color: #064eb0;
    text-decoration: underline;
}

/* Existing global button rules must not alter these controls. */
.otp-auth-card button {
    transform: none;
}

@media (max-width: 900px) {
    .otp-auth-page {
        padding: 36px 28px 48px;

        background:
            linear-gradient(
                90deg,
                rgba(255,255,255,.98) 0%,
                rgba(255,255,255,.94) 48%,
                rgba(255,255,255,.63) 100%
            ),
            url('{{ asset('images/cspc-campus-hero.png') }}')
            center right / cover no-repeat;
    }
}

@media (max-width: 620px) {
    .otp-auth-page {
        min-height: 100vh;
        padding: 20px 16px 32px;

        background:
            linear-gradient(
                rgba(247,250,254,.95),
                rgba(247,250,254,.95)
            ),
            url('{{ asset('images/cspc-campus-hero.png') }}')
            center / cover no-repeat;
    }

    .otp-auth-card {
        max-width: none;
        padding: 24px 20px;
        border-radius: 15px;
    }

    .otp-auth-brand {
        margin-bottom: 21px;
    }

    .otp-auth-brand img {
        width: 250px;
    }

    .otp-auth-heading h1 {
        font-size: 22px;
    }

    .otp-digit-grid {
        gap: 6px;
    }

    .otp-digit-input {
        height: 53px;
        font-size: 21px;
    }
}

@media (max-width: 390px) {
    .otp-auth-page {
        padding-inline: 10px;
    }

    .otp-auth-card {
        padding-inline: 16px;
    }

    .otp-digit-grid {
        gap: 5px;
    }

    .otp-digit-input {
        height: 49px;
        border-radius: 8px;
        font-size: 19px;
    }
}
</style>

<div class="otp-auth-page">
    <section class="otp-auth-card" aria-labelledby="otp-verification-title">

        <div class="otp-auth-brand">
            <img
                src="{{ asset('images/spmu-acpmp-logo.png') }}"
                alt="SPMU-ACPMP — Supply and Property Management Unit, Asset Custody Monitoring Program"
            >
        </div>

        <header class="otp-auth-heading">
            <h1 id="otp-verification-title">Verify your CSPC account</h1>
            <p>
                Enter the 6-digit OTP sent to your registered institutional email.
            </p>
        </header>

        @if(session('status'))
            <div class="otp-card-notice" role="status">
                <span class="otp-card-notice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="M6.8 12.3l3.1 3.1 7.3-7.3"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @elseif($errors->any())
            <div class="otp-card-notice is-error" role="alert">
                <span class="otp-card-notice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path
                            d="M12 7.5v5"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        />
                        <circle cx="12" cy="16.5" r="1" fill="currentColor"/>
                    </svg>
                </span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form
            method="post"
            action="{{ route('account.verification.verify') }}"
            class="otp-form"
            id="otp-verification-form"
        >
            @csrf

            <label class="otp-field">
                <span class="otp-field-label">Official CSPC Email</span>

                <span class="otp-email-shell">
                    <span class="otp-email-icon" aria-hidden="true">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M3 7l9 6 9-6"/>
                        </svg>
                    </span>

                    <input
                        class="otp-email-input"
                        type="email"
                        name="email"
                        value="{{ $verificationEmail }}"
                        placeholder="name@my.cspc.edu.ph"
                        autocomplete="email"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                    >
                </span>

                @error('email')
                    <span class="otp-field-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="otp-field">
                <span class="otp-field-label">
                    6-Digit Verification Code
                </span>

                <div
                    class="otp-digit-grid"
                    role="group"
                    aria-label="6-digit verification code"
                    data-otp-grid
                >
                    @for($index = 0; $index < 6; $index++)
                        <input
                            class="otp-digit-input"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                            value="{{ $otpDigits[$index] ?? '' }}"
                            aria-label="Verification digit {{ $index + 1 }}"
                            data-otp-digit
                            required
                        >
                    @endfor
                </div>

                <input
                    type="hidden"
                    name="otp"
                    id="otp-combined-value"
                    value="{{ $otpValue }}"
                >

                @error('otp')
                    <span class="otp-field-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="otp-action-stack">
                <button
                    class="otp-primary-button"
                    type="submit"
                >
                    Verify Account
                </button>
            </div>
        </form>

        <form
            method="post"
            action="{{ route('account.verification.resend') }}"
            class="otp-action-stack"
            id="otp-resend-form"
        >
            @csrf

            <input
                type="hidden"
                name="email"
                id="otp-resend-email"
                value="{{ $verificationEmail }}"
            >

            <button
                class="otp-secondary-button"
                type="submit"
            >
                Resend OTP
            </button>
        </form>

        <div class="otp-help">
            <span>The code expires after 10 minutes.</span>
            <span>After 5 incorrect attempts, request a new OTP.</span>
        </div>

        <div class="otp-back">
            <a href="{{ route('login') }}">Back to Sign In</a>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const digits = Array.from(
        document.querySelectorAll('[data-otp-digit]')
    );

    const combined = document.getElementById('otp-combined-value');
    const form = document.getElementById('otp-verification-form');

    const emailInput = form?.querySelector(
        'input[name="email"]'
    );

    const resendEmail = document.getElementById('otp-resend-email');

    const syncOtp = () => {
        if (!combined) return;

        combined.value = digits
            .map(input => input.value.replace(/\D/g, '').slice(0, 1))
            .join('');
    };

    const syncEmail = () => {
        if (!emailInput || !resendEmail) return;
        resendEmail.value = emailInput.value.trim();
    };

    digits.forEach((input, index) => {
        input.addEventListener('input', event => {
            const raw = event.target.value.replace(/\D/g, '');

            if (raw.length > 1) {
                const available = raw.slice(0, digits.length - index);

                available.split('').forEach((digit, offset) => {
                    if (digits[index + offset]) {
                        digits[index + offset].value = digit;
                    }
                });

                const nextIndex = Math.min(
                    index + available.length,
                    digits.length - 1
                );

                digits[nextIndex]?.focus();
            }
            else {
                event.target.value = raw.slice(0, 1);

                if (raw && index < digits.length - 1) {
                    digits[index + 1].focus();
                }
            }

            syncOtp();
        });

        input.addEventListener('keydown', event => {
            if (
                event.key === 'Backspace' &&
                !input.value &&
                index > 0
            ) {
                digits[index - 1].focus();
            }

            if (
                event.key === 'ArrowLeft' &&
                index > 0
            ) {
                event.preventDefault();
                digits[index - 1].focus();
            }

            if (
                event.key === 'ArrowRight' &&
                index < digits.length - 1
            ) {
                event.preventDefault();
                digits[index + 1].focus();
            }
        });

        input.addEventListener('paste', event => {
            const pasted = (
                event.clipboardData ||
                window.clipboardData
            )
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            if (!pasted) return;

            event.preventDefault();

            pasted.split('').forEach((digit, offset) => {
                if (digits[offset]) {
                    digits[offset].value = digit;
                }
            });

            syncOtp();

            const focusIndex = Math.min(
                pasted.length,
                digits.length
            ) - 1;

            digits[Math.max(0, focusIndex)]?.focus();
        });
    });

    emailInput?.addEventListener('input', syncEmail);

    form?.addEventListener('submit', () => {
        syncOtp();
        syncEmail();
    });

    document
        .getElementById('otp-resend-form')
        ?.addEventListener('submit', syncEmail);

    syncOtp();
    syncEmail();

    const firstEmpty = digits.find(input => !input.value);

    if (firstEmpty) {
        firstEmpty.focus();
    }
});
</script>

@endsection