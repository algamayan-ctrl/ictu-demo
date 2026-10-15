@extends('layouts.app', ['title' => 'Reset Password'])

@section('content')

@include('auth.partials.public-auth-background')

@include('auth.partials.password-recovery-styles')

<section class="auth-recovery-page">
    <div class="auth-recovery-card">

        <img
            class="auth-recovery-logo"
            src="{{ \App\Support\StaticAsset::url('images/spmu-acpmp-logo.png') }}"
            alt="SPMU-ACPMP"
        >

        <h1 class="auth-recovery-heading">
            Set a new password
        </h1>

        <p class="auth-recovery-intro">
            Create a new password for your SPMU-ACPMP account.
        </p>

        <form
            method="POST"
            action="{{ route('password.update') }}"
            class="auth-recovery-form"
        >
            @csrf

            <input
                type="hidden"
                name="token"
                value="{{ $token }}"
            >

            <div
                class="auth-recovery-field
                    @error('email') has-error @enderror"
            >
                <label for="reset-email">
                    Email
                </label>

                <input
                    id="reset-email"
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                >

                @error('email')
                    <p
                        class="auth-recovery-error"
                        role="alert"
                    >
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div
                class="auth-recovery-field
                    @error('password') has-error @enderror"
            >
                <label for="new-password">
                    New Password
                </label>

                <div class="auth-recovery-password-wrap">
                    <input
                        id="new-password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        required
                        autofocus
                    >

                    <button
                        type="button"
                        class="auth-recovery-password-toggle"
                        data-recovery-password-toggle
                        data-target="new-password"
                        aria-label="Show password"
                        title="Show password"
                    >
                        <x-icon name="eye" />
                    </button>
                </div>

                <p class="auth-recovery-help">
                    Use at least 12 characters with letters,
                    numbers, and symbols.
                </p>

                @error('password')
                    <p
                        class="auth-recovery-error"
                        role="alert"
                    >
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="auth-recovery-field">
                <label for="new-password-confirmation">
                    Confirm New Password
                </label>

                <div class="auth-recovery-password-wrap">
                    <input
                        id="new-password-confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="auth-recovery-password-toggle"
                        data-recovery-password-toggle
                        data-target="new-password-confirmation"
                        aria-label="Show password confirmation"
                        title="Show password"
                    >
                        <x-icon name="eye" />
                    </button>
                </div>
            </div>

            <button
                class="auth-recovery-submit"
                type="submit"
            >
                Reset password
            </button>
        </form>

        <div class="auth-recovery-back">
            <a href="{{ route('login') }}">
                Back to sign in
            </a>
        </div>

    </div>
</section>

<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest(
        '[data-recovery-password-toggle]'
    );

    if (!button) {
        return;
    }

    const input = document.getElementById(
        button.dataset.target || ''
    );

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const showing = input.type === 'text';

    input.type = showing ? 'password' : 'text';

    button.setAttribute(
        'aria-label',
        showing ? 'Show password' : 'Hide password'
    );

    button.setAttribute(
        'title',
        showing ? 'Show password' : 'Hide password'
    );
});
</script>

@endsection