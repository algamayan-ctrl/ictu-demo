@extends('layouts.app', ['title' => 'Terms of Service'])

@php($contact = config('app.privacy_contact'))

@section('content')
@include('legal.partials.styles')
<article class="legal-page">
    <h1>Terms of Service</h1>
    <p class="legal-meta">{{ config('app.name') }} &middot; Effective 15 October 2026</p>

    <p>
        These terms govern the use of {{ config('app.name') }}, the property borrowing and custody system of the
        Supply and Property Management Unit (SPMU) of Camarines Sur Polytechnic Colleges (CSPC).
        By creating an account or signing in, you agree to these terms and to the
        <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
    </p>

    <h2>1. Who may use the system</h2>
    <ul>
        <li>CSPC employees, and external borrowers whose registration has been reviewed and approved by the SPMU.</li>
        <li>A registration is not an approval. Borrowing access starts only after your email is verified and
            your registration is approved.</li>
        <li>CSPC may limit, suspend or close an account that breaks these terms or CSPC property rules.</li>
    </ul>

    <h2>2. Your account</h2>
    <ul>
        <li>Give accurate, current information, and register only for yourself.</li>
        <li>Keep your password private, and never share verification codes or reset links.
            You are responsible for actions taken through your account.</li>
        <li>Tell the SPMU immediately if you suspect someone else has used your account.</li>
        <li>If you sign in with Google, you must also follow Google's terms for your Google account.</li>
    </ul>

    <h2>3. Borrowing and accountability</h2>
    <ul>
        <li>Borrowed property remains CSPC property. Use it only for the approved purpose and return it on time,
            in the condition in which it was released.</li>
        <li>Late returns, loss or damage are handled under CSPC property and accountability rules and may lead to
            penalties, settlement obligations or borrowing restrictions recorded in the system.</li>
        <li>Requests, approvals, releases, returns, signatures and generated documents recorded in the system
            serve as official records of the transaction.</li>
    </ul>

    <h2>4. Acceptable use</h2>
    <p>You must not:</p>
    <ul>
        <li>upload false, altered or misleading documents, IDs, signatures or evidence;</li>
        <li>access records or functions your role does not allow, or try to bypass security controls;</li>
        <li>interfere with the system, overload it, or use automated tools against it;</li>
        <li>use the system or its data for any purpose unrelated to CSPC property management.</li>
    </ul>

    <h2>5. Availability</h2>
    <p>
        The SPMU aims to keep the system available but does not guarantee uninterrupted service.
        It may be unavailable during maintenance or for reasons beyond CSPC's control.
        Official deadlines follow the operational calendar shown in the system (Asia/Manila time).
    </p>

    <h2>6. Changes</h2>
    <p>
        These terms may be updated. The effective date above shows the latest version.
        Continuing to use the system after a change means you accept the updated terms.
    </p>

    <h2>7. Governing rules</h2>
    <p>
        These terms are governed by the laws of the Republic of the Philippines and by CSPC's policies on
        property management, records and data privacy.
    </p>

    <h2>8. Contact</h2>
    <p>
        Questions about these terms: Supply and Property Management Unit, Camarines Sur Polytechnic Colleges{!! $contact ? ', <a href="mailto:'.e($contact).'">'.e($contact).'</a>' : '' !!}.
    </p>

    <p class="legal-related">See also the <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>
</article>
@endsection
