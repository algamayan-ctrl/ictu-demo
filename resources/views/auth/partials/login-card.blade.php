<style>
/* SHARED LOGIN CARD TYPOGRAPHY AND SPACING START */
.spmu-shared-login-card {
    width: 100%;
}

.spmu-shared-login-card .google-auth-error {
    margin-top: -4px; /* The form's 14px grid gap leaves 10px below Google. */
    color: #c62828;
    font-size: 13px;
    line-height: 1.45;
}

.spmu-shared-login-card img {
    display: block !important;
    width: min(100%, 185px) !important;
    max-width: 185px !important;
    height: auto !important;
    margin: 0 auto 14px !important;
}

.spmu-shared-login-card h1,
.spmu-shared-login-card h2,
.spmu-shared-login-card .login-card-title {
    margin: 0 0 8px !important;
    font-size: 17px !important;
    line-height: 1.2 !important;
    font-weight: 800 !important;
    letter-spacing: -0.02em !important;
    text-align: center !important;
    color: #0b2654 !important;
}

.spmu-shared-login-card p,
.spmu-shared-login-card .login-card-subtitle {
    margin: 0 0 18px !important;
    font-size: 13px !important;
    line-height: 1.55 !important;
    text-align: center !important;
    color: #607493 !important;
}

.spmu-shared-login-card form {
    display: grid !important;
    row-gap: 14px !important;
}

.spmu-shared-login-card label {
    display: block !important;
    margin: 0 0 6px !important;
    font-size: 13px !important;
    line-height: 1.35 !important;
    font-weight: 700 !important;
    color: #0b2654 !important;
}

.spmu-shared-login-card input:not([type="checkbox"]):not([type="radio"]),
.spmu-shared-login-card select,
.spmu-shared-login-card .form-control {
    min-height: 52px !important;
    font-size: 14px !important;
    line-height: 1.4 !important;
}

.spmu-shared-login-card .password-toggle,
.spmu-shared-login-card .input-icon,
.spmu-shared-login-card .field-icon,
.spmu-shared-login-card button[type="button"] {
    font-size: 14px !important;
}

.spmu-shared-login-card .remember-row,
.spmu-shared-login-card .checkbox-row,
.spmu-shared-login-card .form-check {
    margin-top: 2px !important;
    margin-bottom: 2px !important;
    font-size: 13px !important;
    line-height: 1.4 !important;
}

.spmu-shared-login-card .remember-row label,
.spmu-shared-login-card .checkbox-row label,
.spmu-shared-login-card .form-check label {
    margin: 0 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #445774 !important;
}

.spmu-shared-login-card button,
.spmu-shared-login-card .btn,
.spmu-shared-login-card a.btn {
    min-height: 52px !important;
    font-size: 15px !important;
    line-height: 1.2 !important;
    font-weight: 700 !important;
    border-radius: 12px !important;
}

.spmu-shared-login-card hr,
.spmu-shared-login-card .divider {
    margin: 8px 0 !important;
}

.spmu-shared-login-card .or-divider,
.spmu-shared-login-card .login-divider {
    margin: 6px 0 4px !important;
    font-size: 13px !important;
    color: #7183a1 !important;
}

.spmu-shared-login-card .help-text,
.spmu-shared-login-card .login-help,
.spmu-shared-login-card small {
    font-size: 12.5px !important;
    line-height: 1.55 !important;
    color: #607493 !important;
}

.spmu-shared-login-card .support-copy,
.spmu-shared-login-card .footer-copy {
    margin-top: 2px !important;
    font-size: 12.5px !important;
    line-height: 1.6 !important;
    text-align: center !important;
    color: #445774 !important;
}

.spmu-shared-login-card .card,
.spmu-shared-login-card .login-card-panel,
.spmu-shared-login-card > div:first-child {
    padding-top: 24px !important;
    padding-right: 26px !important;
    padding-bottom: 24px !important;
    padding-left: 26px !important;
}

@media (max-width: 640px) {
    .spmu-shared-login-card img {
        width: min(100%, 170px) !important;
        max-width: 170px !important;
        margin-bottom: 12px !important;
    }

    .spmu-shared-login-card h1,
    .spmu-shared-login-card h2,
    .spmu-shared-login-card .login-card-title {
        font-size: 16px !important;
    }

    .spmu-shared-login-card p,
    .spmu-shared-login-card .login-card-subtitle {
        font-size: 12.5px !important;
        margin-bottom: 16px !important;
    }

    .spmu-shared-login-card .card,
    .spmu-shared-login-card .login-card-panel,
    .spmu-shared-login-card > div:first-child {
        padding: 22px 20px !important;
    }

    .spmu-shared-login-card form {
        row-gap: 12px !important;
    }
}
/* SHARED LOGIN CARD TYPOGRAPHY AND SPACING END */
</style>

<div class="spmu-shared-login-card">
<div class="login-card">


        <div class="login-heading">
                    <!-- LOGIN CARD LOGO SWAP START -->
        <div class="landing-login-badge landing-login-badge--logo">
            <img
                src="{{ \App\Support\StaticAsset::url('images/spmu-acpmp-logo.png') }}"
                alt="SPMU-ACPMP"
                class="landing-login-badge-logo"
            >
        </div>
        <!-- LOGIN CARD LOGO SWAP END -->
<h1 id="landing-login-title">Welcome to SPMU-ACPMP</h1>

            <p>Sign in using your authorized SPMU-ACPMP account.</p>
        </div>


        <form method="POST" action="{{ route('login') }}" class="login-form" data-preserve-submit-label="true">
            @csrf

            @if(session('status'))
                <div role="status">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="login-error" role="alert">{{ session('error') }}</div>
            @endif
            @foreach($errors->getMessages() as $field => $messages)
                @unless(in_array($field, ['email', 'password', 'google'], true))
                    @foreach($messages as $message)
                        <div class="login-error" role="alert">{{ $message }}</div>
                    @endforeach
                @endunless
            @endforeach
            {{-- EMAIL --}}
            <div class="login-field">

                <label for="email">
                    Email
                </label>

                <div class="login-input-wrap @error('email') has-error @enderror">

                    <span class="login-input-icon" aria-hidden="true">
                        <x-icon name="email" />
                    </span>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                    >

                </div>



                @error('email')
                    <div class="login-error" role="alert">{{ $message }}</div>
                @enderror

            </div>


            {{-- PASSWORD --}}
            <div class="login-field">

                <div class="login-label-row">
                    <label for="password">
                        Password
                    </label>

                    @if(Route::has('password.request'))
                        <a
                            href="{{ route('password.request') }}"
                            class="login-forgot"
                        >
                            Forgot password?
                        </a>
                    @endif
                </div>


                <div class="login-input-wrap @error('password') has-error @enderror">

                    <span class="login-input-icon" aria-hidden="true">
                        <x-icon name="lock" />
                    </span>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="login-password-toggle"
                        id="loginPasswordToggle"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <x-icon name="eye" />
                    </button>

                </div>



                @error('password')
                    <div class="login-error" role="alert">{{ $message }}</div>
                @enderror

            </div>


            {{-- REMEMBER ME --}}
            @error('registration')<div class="login-error" role="alert">{{ $message }}</div>@enderror
            <label class="login-remember">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    {{ old('remember') ? 'checked' : '' }}
                >

                <span>
                    Keep me signed in
                </span>
            </label>


            {{-- SIGN IN --}}
            <button
                type="submit"
                class="login-submit"
            >

                <span>
                    Sign in
                </span>

            </button>


            {{-- Keep the requested Google action visible; the route reports missing configuration honestly. --}}
                <div class="login-alt-divider">
                    <span>or</span>
                </div>

                <a
                    class="login-google"
                    href="{{ route('auth.google.redirect') }}"
                >
                    <span class="login-google-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62Z"/>
                            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.02-3.7H.96v2.33A9 9 0 0 0 9 18Z"/>
                            <path fill="#FBBC05" d="M3.98 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.02-2.33Z"/>
                            <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.46 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.02 2.33C4.68 5.16 6.66 3.58 9 3.58Z"/>
                        </svg>
                    </span>

                    <span>
                        Continue with Google
                    </span>
                </a>
            <p class="mt-3"><a href="{{ route('register') }}">Don't have an account yet? Sign up</a></p>


            @error('google')
                <div class="google-auth-error" role="alert">{{ $message }}</div>
            @enderror

            {{-- HELP --}}
            <div class="login-help">

                <div class="login-help-divider">
                    <span>
                        Need help?
                    </span>
                </div>

                <p>
                    For CSPC employees and approved external borrowers.
                </p>

                <p>
                    Contact the SPMU Admin for account access assistance.
                </p>
                <div class="login-otp-verification">
                    <a href="{{ route('account.verification.show') }}">
                        <span>Pending account verification?</span>
                        <strong>Verify with OTP</strong>
                    </a>
                </div>

            </div>

        </form>

    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // An inline Google error may grow the card downward, but must not change
    // the landing slot's centering or the campus background's cover geometry.
    const googleError = document.querySelector('.google-auth-error');
    const sharedCard = googleError?.closest('.spmu-shared-login-card');
    if (sharedCard) {
        const preserveLandingGeometry = () => {
            googleError.hidden = true;
            sharedCard.style.height = '';
            const height = sharedCard.getBoundingClientRect().height;
            sharedCard.style.height = `${height}px`;
            googleError.hidden = false;
        };
        preserveLandingGeometry();
        window.addEventListener('resize', preserveLandingGeometry);
        document.fonts.ready.then(preserveLandingGeometry);
    }
    const password = document.getElementById('password');
    const toggle = document.getElementById('loginPasswordToggle');

    if (!password || !toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        const showing = password.type === 'text';

        password.type = showing ? 'password' : 'text';

        const label = showing ? 'Show password' : 'Hide password';

        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
    });
});
</script>
</div>

<style>
/* SPMU REMEMBER CHECKBOX ALIGNMENT START */

/*
 * Align the Remember checkbox and its text on one clean baseline.
 * Scoped only to the SPMU login card.
 */

.spmu-shared-login-card *:has(> input[type="checkbox"][name="remember"]) {
    display: flex !important;
    align-items: center !important;
}

/* Checkbox */
.spmu-shared-login-card input[type="checkbox"][name="remember"],
.spmu-shared-login-card #remember {
    width: 17px !important;
    height: 17px !important;

    margin-top: 0 !important;
    margin-bottom: 0 !important;
    margin-left: 0 !important;
    margin-right: 8px !important;

    padding: 0 !important;

    flex: 0 0 17px !important;

    vertical-align: middle !important;

    position: relative !important;
    top: 0 !important;
}

/* "Keep me signed in" text */
.spmu-shared-login-card label[for="remember"] {
    margin: 0 !important;
    padding: 0 !important;

    display: inline-flex !important;
    align-items: center !important;

    min-height: 17px !important;

    line-height: 1.2 !important;
    vertical-align: middle !important;

    cursor: pointer;
}

/* Bootstrap form-check compatibility */
.spmu-shared-login-card .form-check:has(input[name="remember"]) {
    display: flex !important;
    align-items: center !important;

    min-height: 0 !important;

    padding-left: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

.spmu-shared-login-card .form-check:has(input[name="remember"])
.form-check-input {
    float: none !important;
    margin-left: 0 !important;
    margin-top: 0 !important;
}

.spmu-shared-login-card .form-check:has(input[name="remember"])
.form-check-label {
    margin: 0 !important;
    line-height: 1.2 !important;
}

/* SPMU REMEMBER CHECKBOX ALIGNMENT END */
</style>

<style>
/* LOGIN OTP VERIFICATION FINAL */

.spmu-shared-login-card .login-help .login-otp-verification {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #d8e2ee;
    text-align: center;
}

.spmu-shared-login-card .login-help .login-otp-verification a {
    display: inline-flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 4px;
    color: #0969e8;
    font-size: 11px;
    line-height: 1.4;
    text-decoration: none;
}

.spmu-shared-login-card .login-help .login-otp-verification a span {
    font-weight: 600;
}

.spmu-shared-login-card .login-help .login-otp-verification a strong {
    font-weight: 800;
}

.spmu-shared-login-card .login-help .login-otp-verification a:hover {
    text-decoration: underline;
}
</style>
