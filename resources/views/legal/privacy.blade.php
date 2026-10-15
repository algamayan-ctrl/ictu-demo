@extends('layouts.app', ['title' => 'Privacy Policy'])

@php($contact = config('app.privacy_contact'))

@section('content')
@include('legal.partials.styles')
<article class="legal-page">
    <h1>Privacy Policy</h1>
    <p class="legal-meta">{{ config('app.name') }} &middot; Effective 15 October 2026</p>

    <p>
        {{ config('app.name') }} (Supply and Property Management Unit &ndash; Asset Custody Monitoring Program)
        is the system the Supply and Property Management Unit (SPMU) of Camarines Sur Polytechnic Colleges (CSPC)
        uses to manage borrowing, release, custody, return and accountability of college property.
        This policy explains what personal information the system collects, why, and how it is protected,
        in line with the Data Privacy Act of 2012 (Republic Act No. 10173).
    </p>

    <h2>1. Information we collect</h2>
    <ul>
        <li><strong>Account details:</strong> full name, email address, employee or borrower number, designation,
            employment type and status, organizational unit, and an optional mobile number.</li>
        <li><strong>External borrower details:</strong> company or organization name and an uploaded valid ID.</li>
        <li><strong>Sign-in information:</strong> a securely hashed password (never stored in readable form),
            one-time email verification codes, and password-reset tokens, which expire automatically.</li>
        <li><strong>Google sign-in information:</strong> see section 3.</li>
        <li><strong>Transaction records:</strong> borrowing requests, approvals, releases, returns, inspections,
            gate passes, laundry turnover, incidents, penalties and payments, with supporting documents,
            evidence photos, signatures and generated forms.</li>
        <li><strong>Profile picture</strong>, if you upload one.</li>
        <li><strong>Security and audit records:</strong> sign-in times, session information (IP address and browser
            type), document downloads, and a log of actions taken in the system.</li>
    </ul>

    <h2>2. Why we use it</h2>
    <ul>
        <li>To create and verify your account and confirm you control your email address.</li>
        <li>To process, approve and track borrowing transactions and the custody of college property.</li>
        <li>To send email notices: verification codes, password-reset links, approvals, reminders and deadlines.</li>
        <li>To determine accountability for late, lost or damaged items, as required by CSPC property rules.</li>
        <li>To keep the system secure, investigate misuse, and produce official reports.</li>
    </ul>
    <p>We do not sell personal information, and we do not use it for advertising.</p>

    <h2>3. Google sign-in</h2>
    <p>
        If you choose &ldquo;Continue with Google&rdquo;, Google shares only your basic profile:
        your Google account identifier, email address and name (the <code>openid</code>, <code>email</code> and
        <code>profile</code> permissions). We use them only to identify you and to fill in your registration form.
        We store your Google account identifier to link it to your {{ config('app.name') }} account.
        We do not access your Gmail, Google Drive, contacts or any other Google data, and we do not receive
        your Google password.
    </p>
    <p>
        Our use of information received from Google APIs adheres to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy" rel="noopener" target="_blank">Google API Services User Data Policy</a>,
        including the Limited Use requirements. You can remove the system's access at any time from your
        <a href="https://myaccount.google.com/connections" rel="noopener" target="_blank">Google Account connections</a>;
        you can then still sign in with your email and password.
    </p>

    <h2>4. Who can see your information</h2>
    <ul>
        <li><strong>Authorized CSPC personnel</strong> (SPMU staff, approving officers and system administrators),
            only as far as their role requires.</li>
        <li><strong>Service providers that run the system:</strong> the hosting provider (server and storage),
            Cloudflare (secure network connection), Google (sign-in and email delivery).
            They process data only to provide their service.</li>
        <li><strong>Government or legal authorities</strong>, when required by law or an official audit.</li>
    </ul>

    <h2>5. How we protect it</h2>
    <p>
        All connections use HTTPS. Passwords are hashed, sessions are encrypted and end after a period of inactivity,
        uploaded files are stored privately and served only to authorized users, and access is limited by role.
        Every significant action is recorded in an audit log.
    </p>

    <h2>6. How long we keep it</h2>
    <p>
        Verification codes expire after 10 minutes and password-reset links after 60 minutes.
        Account and transaction records are kept for as long as needed for property accountability and
        are then retained or disposed of according to CSPC records-management rules and applicable government
        regulations.
    </p>

    <h2>7. Your rights</h2>
    <p>
        Under the Data Privacy Act, you may ask to be informed about, access, correct, object to the processing of,
        or request the erasure or blocking of your personal information, and you may file a complaint with the
        National Privacy Commission. Some records cannot be erased while they are needed for unsettled
        accountability or required by law. You can update most account details yourself in Account Settings.
    </p>

    <h2>8. Cookies and browser storage</h2>
    <p>
        The system uses only essential cookies to keep you signed in and to protect forms against forgery,
        and your browser's local storage to remember display preferences such as the theme and sidebar.
        It does not use advertising or third-party tracking cookies.
    </p>

    <h2>9. Contact</h2>
    <p>
        For privacy questions or requests, contact the Supply and Property Management Unit, Camarines Sur Polytechnic Colleges, Nabua, Camarines Sur, Philippines
        @if($contact)
            at <a href="mailto:{{ $contact }}">{{ $contact }}</a>,
        @endif
        or the CSPC Data Protection Officer.
    </p>

    <h2>10. Changes to this policy</h2>
    <p>
        We may update this policy. The effective date above shows the latest version; significant changes will be
        announced in the system.
    </p>

    <p class="legal-related">See also the <a href="{{ route('legal.terms') }}">Terms of Service</a>.</p>
</article>
@endsection
