@extends('layouts.app', ['title' => 'Forgot Password'])

@section('content')
{{-- This is the Password Recovery UI for registered users. --}}

@include('auth.partials.public-auth-background')

<style>

body:has(.forgot-login-page) .site-header,
body:has(.forgot-login-page) .app-topbar,
body:has(.forgot-login-page) .app-main > .notice,
body:has(.forgot-login-page) > footer {
    display: none !important;
}

body:has(.forgot-login-page) .app-main {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

.forgot-login-page {
    width: 100%;
    min-height: 100vh;

    display: flex;
    align-items: flex-start;
    justify-content: flex-start;

    padding: 58px 0 48px 40px;
    box-sizing: border-box;
}

.forgot-login-card {
    width: 420px;
    max-width: calc(100vw - 32px);

    box-sizing: border-box;

    padding: 16px 20px 20px;

    border: 1px solid #d0dce9;
    border-radius: 17px;

    background: #ffffff;

    box-shadow:
        0 12px 30px rgba(34, 63, 98, .07);
}

.forgot-login-logo-wrap {
    display: flex;
    align-items: center;
    justify-content: center;

    min-height: 54px;

    margin-bottom: 12px;
}

.forgot-login-logo {
    display: block;

    width: 255px;
    max-width: 100%;
    height: auto;
}

.forgot-login-heading {
    margin: 0;

    color: #06274f;

    font-size: 24px;
    font-weight: 800;
    line-height: 1.15;

    text-align: center;
    letter-spacing: -.025em;
}

.forgot-login-subtitle {
    margin: 7px auto 16px;

    max-width: 330px;

    color: #57708f;

    font-size: 12.5px;
    font-weight: 400;
    line-height: 1.45;

    text-align: center;
}

.forgot-login-status {
    margin: 0 0 12px;
    padding: 10px 12px;

    border: 1px solid #a9dbc0;
    border-radius: 7px;

    background: #edf9f3;
    color: #147147;

    font-size: 11.5px;
    line-height: 1.45;
}

.forgot-login-form {
    display: grid;
    gap: 12px;
}

.forgot-login-field {
    display: grid;
    gap: 7px;
}

.forgot-login-field label {
    margin: 0;

    color: #06274f;

    font-size: 12.5px;
    font-weight: 800;
    line-height: 1.2;
}

.forgot-login-input-wrap {
    position: relative;
}

.forgot-login-input-wrap input {
    display: block;

    width: 100%;
    height: 55px;

    box-sizing: border-box;

    padding:
        0
        12px
        0
        43px;

    border: 1px solid #c6d5e7;
    border-radius: 9px;

    outline: none;

    background: #ffffff;
    color: #102d50;

    font-size: 13px;
    font-family: inherit;

    transition:
        border-color .15s ease,
        box-shadow .15s ease;
}

.forgot-login-input-wrap input:focus {
    border-color: #2878ef;

    box-shadow:
        0 0 0 2px rgba(40, 120, 239, .14);
}

.forgot-login-input-wrap.has-error input {
    border-color: #dc3545;
}

.forgot-login-input-icon {
    position: absolute;

    top: 50%;
    left: 13px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 20px;
    height: 20px;

    color: #7890ad;

    transform: translateY(-50%);

    pointer-events: none;
}

.forgot-login-input-icon svg {
    width: 19px;
    height: 19px;
}

.forgot-login-error {
    margin: 0;

    color: #dc3545;

    font-size: 11.5px;
    line-height: 1.35;
}

.forgot-login-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 100%;
    height: 48px;

    margin-top: 1px;
    padding: 0 16px;

    border: 1px solid #246cec !important;
    border-radius: 9px;

    background:
        linear-gradient(
            90deg,
            #205ce5 0%,
            #2d79ef 100%
        ) !important;

    color: #ffffff !important;

    font-size: 12.5px;
    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 8px 18px rgba(38, 111, 233, .16);

    transition:
        background-color .15s ease,
        border-color .15s ease,
        box-shadow .15s ease,
        transform .12s ease;
}

.forgot-login-submit:hover,
.forgot-login-submit:focus-visible {
    border-color: #165bd2 !important;

    background: #165bd2 !important;

    color: #ffffff !important;

    box-shadow:
        0 10px 20px rgba(38, 111, 233, .20);
}

.forgot-login-back {
    display: flex;
    align-items: center;
    justify-content: center;

    margin-top: 17px;
    padding-top: 15px;

    border-top: 1px solid #d7e2ee;
}

.forgot-login-back a {
    color: #0969e8;

    font-size: 12px;
    font-weight: 700;

    text-decoration: none;
}

.forgot-login-back a:hover,
.forgot-login-back a:focus-visible {
    color: #064eb0;
    text-decoration: underline;
}

@media (max-width: 720px) {
    .forgot-login-page {
        justify-content: center;

        padding:
            16px
            12px
            36px;
    }

    .forgot-login-card {
        width: 100%;
        max-width: 420px;
    }
}
</style>

<section class="forgot-login-page">

    <div class="forgot-login-card">

        <div class="forgot-login-logo-wrap">
            <img
                src="{{ \App\Support\StaticAsset::url('images/spmu-acpmp-logo.png') }}"
                alt="SPMU-ACPMP"
                class="forgot-login-logo"
            >
        </div>

        <h1 class="forgot-login-heading">
            Forgot your password?
        </h1>

        <p class="forgot-login-subtitle">
            Enter your registered email address
            and we will send password reset instructions
            to your email inbox.
        </p>

        @if(session('status'))
            <div
                class="forgot-login-status"
                role="status"
            >
                {{ session('status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.email') }}"
            class="forgot-login-form"
        >
            @csrf

            <div class="forgot-login-field">

                <label for="forgot-email">
                    Email
                </label>

                <div
                    class="
                        forgot-login-input-wrap
                        @error('email') has-error @enderror
                    "
                >
                    <span
                        class="forgot-login-input-icon"
                        aria-hidden="true"
                    >
                        <x-icon name="email" />
                    </span>

                    <input
                        id="forgot-email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                        placeholder="name@my.cspc.edu.ph"
                    >
                </div>

                @error('email')
                    <div
                        class="forgot-login-error"
                        role="alert"
                    >
                        {{ $message }}
                    </div>
                @enderror

            </div>

            <button
                type="submit"
                class="forgot-login-submit"
            >
                Send reset instructions
            </button>

        </form>

        <div class="forgot-login-back">
            <a href="{{ route('login') }}">
                Back to sign in
            </a>
        </div>

    </div>

</section>

@endsection
