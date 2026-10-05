@extends('layouts.app', ['title' => 'Verify Account'])

@section('content')
<section class="otp-verification-page">
    <div class="otp-verification-shell">
        <div class="otp-verification-card">
            <div class="otp-verification-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                    <path d="m4 7 8 6 8-6"></path>
                </svg>
            </div>

            <div class="otp-verification-heading">
                <p class="otp-eyebrow">Institutional account verification</p>
                <h1>Verify your CSPC account</h1>
                <p>
                    Enter the 6-digit OTP sent to the institutional email
                    registered by ICTU.
                </p>
            </div>

            @if(session('status'))
                <div class="otp-status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form method="post"
                  action="{{ route('account.verification.verify') }}"
                  class="otp-verification-form">
                @csrf

                <label class="otp-field">
                    <span>Official CSPC Email</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        placeholder="name@cspc.edu.ph"
                        autocomplete="email"
                        required
                    >
                    @error('email')
                        <small class="otp-error">{{ $message }}</small>
                    @enderror
                </label>

                <label class="otp-field">
                    <span>6-Digit Verification Code</span>
                    <input
                        type="text"
                        name="otp"
                        value=""
                        placeholder="000000"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        class="otp-code-input"
                        required
                        autofocus
                    >
                    @error('otp')
                        <small class="otp-error">{{ $message }}</small>
                    @enderror
                </label>

                <button type="submit" class="otp-primary-button">
                    Verify Account
                </button>

                <button
                    type="submit"
                    formaction="{{ route('account.verification.resend') }}"
                    formmethod="post"
                    formnovalidate
                    class="otp-resend-button"
                >
                    Resend OTP
                </button>
            </form>

            <div class="otp-verification-notes">
                <p>The code expires after 10 minutes.</p>
                <p>After 5 incorrect attempts, request a new OTP.</p>
            </div>

            <a href="{{ route('login') }}" class="otp-back-link">
                Back to Sign In
            </a>
        </div>
    </div>
</section>

<style>
.otp-verification-page{
    width:100%;
    min-height:calc(100vh - 40px);
    display:grid;
    place-items:center;
    box-sizing:border-box;
    padding:32px 18px;
}
.otp-verification-shell{
    width:min(100%,460px);
}
.otp-verification-card{
    display:grid;
    gap:18px;
    padding:30px;
    border:1px solid #d9e3ed;
    border-radius:16px;
    background:#fff;
    box-shadow:0 18px 44px rgba(33,65,98,.10);
}
.otp-verification-icon{
    width:52px;
    height:52px;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:#edf5ff;
    color:#1769e0;
}
.otp-verification-heading{
    display:grid;
    gap:6px;
}
.otp-verification-heading h1{
    margin:0;
    color:#15304f;
    font-size:24px;
    line-height:1.2;
}
.otp-verification-heading p{
    margin:0;
    color:#667b91;
    font-size:13px;
    line-height:1.55;
}
.otp-eyebrow{
    color:#1769e0!important;
    font-size:10px!important;
    font-weight:800;
    letter-spacing:.1em;
    text-transform:uppercase;
}
.otp-verification-form{
    display:grid;
    gap:14px;
}
.otp-field{
    display:grid;
    gap:6px;
}
.otp-field>span{
    color:#263f5b;
    font-size:12px;
    font-weight:750;
}
.otp-field input{
    width:100%;
    box-sizing:border-box;
    height:44px;
    padding:10px 12px;
    border:1px solid #cbd8e5;
    border-radius:9px;
    background:#fff;
    color:#20384f;
    font:inherit;
}
.otp-field input:focus{
    outline:none;
    border-color:#1769e0;
    box-shadow:0 0 0 3px rgba(23,105,224,.12);
}
.otp-code-input{
    text-align:center;
    font-size:22px!important;
    font-weight:800!important;
    letter-spacing:8px;
}
.otp-primary-button,
.otp-resend-button{
    width:100%;
    min-height:42px;
    border-radius:9px;
    font:inherit;
    font-size:13px;
    font-weight:750;
    cursor:pointer;
}
.otp-primary-button{
    border:1px solid #1769e0;
    background:#1769e0;
    color:#fff;
}
.otp-resend-button{
    border:1px solid #cbd8e5;
    background:#fff;
    color:#274661;
}
.otp-status{
    padding:11px 12px;
    border:1px solid #b9dfc6;
    border-radius:9px;
    background:#eefaf2;
    color:#146c37;
    font-size:12px;
    line-height:1.45;
}
.otp-error{
    color:#b42318;
    font-size:11px;
    line-height:1.4;
}
.otp-verification-notes{
    padding-top:2px;
    color:#7b8c9e;
    font-size:10.5px;
    line-height:1.5;
}
.otp-verification-notes p{
    margin:2px 0;
}
.otp-back-link{
    color:#1769e0;
    font-size:12px;
    font-weight:700;
    text-decoration:none;
    text-align:center;
}
@media(max-width:560px){
    .otp-verification-card{
        padding:22px 18px;
    }
}
html[data-theme="dark"] .otp-verification-card{
    background:#102438;
    border-color:#35516a;
}
html[data-theme="dark"] .otp-verification-heading h1,
html[data-theme="dark"] .otp-field>span{
    color:#edf6ff;
}
html[data-theme="dark"] .otp-field input,
html[data-theme="dark"] .otp-resend-button{
    background:#132a40;
    border-color:#35516a;
    color:#edf6ff;
}
</style>
@endsection