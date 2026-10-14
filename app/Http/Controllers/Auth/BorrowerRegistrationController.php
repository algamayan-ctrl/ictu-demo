<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BorrowerRegistrationRequest;
use App\Models\OrganizationalUnit;
use App\Services\BorrowerRegistrationService;
use App\Services\EmailVerificationService;
use App\Services\GoogleRegistrationContext;
use Illuminate\Http\Request;
use App\Support\OrganizationalStructure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BorrowerRegistrationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $google = $request->query('source') === 'google' ? app(GoogleRegistrationContext::class)->current($request) : null;
        if ($request->query('source') !== 'google') {
            $request->session()->forget('google_registration');
        }
        if ($request->query('source') === 'google' && ! $google) {
            return redirect()->route('login')->withErrors(['google' => 'Your Google registration session expired. Continue with Google again.']);
        }
        return view('auth.register', [
            'googleRegistration' => $google,
            'divisions' => OrganizationalStructure::divisions(),
            'units' => OrganizationalUnit::query()->activeSelectable()->orderBy('unit_name')->get(),
            'uploadLimitMb' => BorrowerRegistrationRequest::uploadLimitMb(),
        ]);
    }

    public function store(BorrowerRegistrationRequest $request, BorrowerRegistrationService $registrations, EmailVerificationService $verification): RedirectResponse
    {
        try {
            $google = $request->input('registration_source') === 'google' ? app(GoogleRegistrationContext::class)->current($request) : null;
            $registration = $registrations->register($request->validated(), $request->file('valid_id'), $google);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email or employee number is already registered. Sign in or contact SAO.']);
        }

        if ($google) {
            $request->session()->forget('google_registration');
            $registrations->notifySubmitted($registration);
            return redirect()->route('login')->with('status', 'Registration submitted to SAO for approval. Your Google email is verified.');
        }
        $sent = $verification->send($registration->user);

        return redirect()->route('account.verification.show', ['email' => $registration->user->email])
            ->with('status', $sent
                ? 'Registration saved. Check your email.'
                : 'Registration saved. The code could not be sent. Resend OTP or contact the SPMU Admin.');
    }
}
